<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\ReleaseCommand;
use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelogInterface;
use Laminas\AutomaticReleases\Environment\VariablesInterface;
use Laminas\AutomaticReleases\Git\CreateTagInterface;
use Laminas\AutomaticReleases\Git\FetchInterface;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesInterface;
use Laminas\AutomaticReleases\Git\PushInterface;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestoneInterface;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseInterface;
use Laminas\AutomaticReleases\Github\CreateReleaseTextInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ReleaseCommandTest extends TestCase
{
    /** @var MockObject&VariablesInterface */
    private VariablesInterface $variables;
    /** @var LoadCurrentGithubEventInterface&MockObject */
    private LoadCurrentGithubEventInterface $loadEvent;
    /** @var FetchInterface&MockObject */
    private FetchInterface $fetch;
    /** @var GetMergeTargetCandidateBranchesInterface&MockObject */
    private GetMergeTargetCandidateBranchesInterface $getMergeTargets;
    /** @var GetGithubMilestoneInterface&MockObject */
    private GetGithubMilestoneInterface $getMilestone;
    /** @var CommitReleaseChangelogInterface&MockObject */
    private CommitReleaseChangelogInterface $commitChangelog;
    /** @var CreateReleaseTextInterface&MockObject */
    private CreateReleaseTextInterface $createReleaseText;
    /** @var CreateTagInterface&MockObject */
    private CreateTagInterface $createTag;
    /** @var MockObject&PushInterface */
    private PushInterface $push;
    /** @var CreateReleaseInterface&MockObject */
    private CreateReleaseInterface $createRelease;
    private ReleaseCommand $command;
    private MilestoneClosedEvent $event;
    private MergeTargetCandidateBranches $branches;
    private Milestone $milestone;
    private SemVerVersion $releaseVersion;
    private SecretKeyId $signingKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables         = $this->createMock(VariablesInterface::class);
        $this->loadEvent         = $this->createMock(LoadCurrentGithubEventInterface::class);
        $this->fetch             = $this->createMock(FetchInterface::class);
        $this->getMergeTargets   = $this->createMock(GetMergeTargetCandidateBranchesInterface::class);
        $this->getMilestone      = $this->createMock(GetGithubMilestoneInterface::class);
        $this->commitChangelog   = $this->createMock(CommitReleaseChangelogInterface::class);
        $this->createReleaseText = $this->createMock(CreateReleaseTextInterface::class);
        $this->createTag         = $this->createMock(CreateTagInterface::class);
        $this->push              = $this->createMock(PushInterface::class);
        $this->createRelease     = $this->createMock(CreateReleaseInterface::class);

        $this->command = new ReleaseCommand(
            $this->variables,
            $this->loadEvent,
            $this->fetch,
            $this->getMergeTargets,
            $this->getMilestone,
            $this->commitChangelog,
            $this->createReleaseText,
            $this->createTag,
            $this->push,
            $this->createRelease,
        );

        $this->event = MilestoneClosedEvent::fromEventJson(<<<'JSON'
{
    "milestone": {
        "title": "1.2.3",
        "number": 123
    },
    "repository": {
        "full_name": "foo/bar"
    },
    "action": "closed"
}
JSON
        );

        $this->branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1.x'),
            BranchName::fromName('1.2.x'),
            BranchName::fromName('1.3.x'),
            BranchName::fromName('master'),
        );

        $this->milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => 'The title',
            'description'  => 'The description',
            'issues'       => [
                'nodes' => [],
            ],
            'pullRequests' => [
                'nodes' => [],
            ],
            'url'          => 'https://example.com/milestone',
        ]);

        $this->releaseVersion = SemVerVersion::fromMilestoneName('1.2.3');
        $this->signingKey     = SecretKeyId::fromBase16String('aabbccddeeff');

        $this->variables->method('signingSecretKey')
            ->willReturn($this->signingKey);
        $this->variables->method('githubToken')
            ->willReturn('github-auth-token');
    }

    public function testCommandName(): void
    {
        self::assertSame('laminas:automatic-releases:release', $this->command->getName());
    }

    public function testWillRelease(): void
    {
        $workspace = Filesystem\create_temporary_file(Env\temp_dir(), 'workspace');

        Filesystem\delete_file($workspace);
        Filesystem\create_directory($workspace);
        Filesystem\create_directory($workspace . '/.git');

        $this->variables->method('githubWorkspacePath')
            ->willReturn($workspace);

        $this->loadEvent->method('__invoke')
            ->willReturn($this->event);

        $this->fetch->expects(self::once())
            ->method('__invoke')
            ->with('https://github-auth-token:x-oauth-basic@github.com/foo/bar.git', $workspace);

        $this->getMergeTargets->method('__invoke')
            ->with($workspace)
            ->willReturn($this->branches);

        $this->getMilestone->method('__invoke')
            ->with(self::equalTo(RepositoryName::fromFullName('foo/bar')), 123)
            ->willReturn($this->milestone);

        /** @psalm-var ChangelogReleaseNotes&MockObject $releaseNotes */
        $releaseNotes = $this->createMock(ChangelogReleaseNotes::class);
        $releaseNotes
            ->expects(self::atLeastOnce())
            ->method('contents')
            ->willReturn('text of the changelog');

        $this->createReleaseText
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo($this->milestone),
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::equalTo($this->releaseVersion),
                self::equalTo(BranchName::fromName('1.2.x')),
                self::equalTo($workspace),
            )
            ->willReturn($releaseNotes);

        $this->commitChangelog
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $releaseNotes,
                self::equalTo($workspace),
                self::equalTo($this->releaseVersion),
                self::equalTo(BranchName::fromName('1.2.x'))
            );

        $this->createTag->expects(self::once())
            ->method('__invoke')
            ->with(
                $workspace,
                self::equalTo(BranchName::fromName('1.2.x')),
                '1.2.3',
                'text of the changelog',
                self::equalTo($this->signingKey)
            );

        $this->push->expects(self::exactly(2))
            ->method('__invoke')
            ->with($workspace, '1.2.3', self::logicalOr(null, '1.2.x'));

        $this->createRelease->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::equalTo($this->releaseVersion),
                'text of the changelog'
            );

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }
}
