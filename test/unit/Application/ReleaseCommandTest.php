<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\ReleaseCommand;
use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelog;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\CreateTag;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateRelease;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
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
    /** @var MockObject&Variables */
    private Variables $variables;
    /** @var LoadCurrentGithubEvent&MockObject */
    private LoadCurrentGithubEvent $loadEvent;
    /** @var Fetch&MockObject */
    private Fetch $fetch;
    /** @var GetMergeTargetCandidateBranches&MockObject */
    private GetMergeTargetCandidateBranches $getMergeTargets;
    /** @var GetGithubMilestone&MockObject */
    private GetGithubMilestone $getMilestone;
    /** @var CommitReleaseChangelog&MockObject */
    private CommitReleaseChangelog $commitChangelog;
    /** @var CreateReleaseText&MockObject */
    private CreateReleaseText $createReleaseText;
    /** @var CreateTag&MockObject */
    private CreateTag $createTag;
    /** @var MockObject&Push */
    private Push $push;
    /** @var CreateRelease&MockObject */
    private CreateRelease $createRelease;
    private ReleaseCommand $command;
    private MilestoneClosedEvent $event;
    private MergeTargetCandidateBranches $branches;
    private Milestone $milestone;
    private SemVerVersion $releaseVersion;
    private SecretKeyId $signingKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables         = $this->createMock(Variables::class);
        $this->loadEvent         = $this->createMock(LoadCurrentGithubEvent::class);
        $this->fetch             = $this->createMock(Fetch::class);
        $this->getMergeTargets   = $this->createMock(GetMergeTargetCandidateBranches::class);
        $this->getMilestone      = $this->createMock(GetGithubMilestone::class);
        $this->commitChangelog   = $this->createMock(CommitReleaseChangelog::class);
        $this->createReleaseText = $this->createMock(CreateReleaseText::class);
        $this->createTag         = $this->createMock(CreateTag::class);
        $this->push              = $this->createMock(Push::class);
        $this->createRelease     = $this->createMock(CreateRelease::class);

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
