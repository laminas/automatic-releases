<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Exception;
use Laminas\AutomaticReleases\Announcement\Contracts\Publish;
use Laminas\AutomaticReleases\Application\Command\ReleaseCommand;
use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelog;
use Laminas\AutomaticReleases\Environment\Contracts\Variables;
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
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Laminas\AutomaticReleases\Twitter\Value\Tweet;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;

/** @psalm-suppress MissingConstructor */
final class ReleaseCommandTest extends TestCase
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeTargets;
    private GetGithubMilestone $getMilestone;
    private CommitReleaseChangelog $commitChangelog;
    private CreateReleaseText $createReleaseText;
    private CreateTag $createTag;
    private Push $push;
    private CreateRelease $createRelease;
    private Publish $publishTweet;
    private ReleaseCommand $command;
    private MilestoneClosedEvent $event;
    private MergeTargetCandidateBranches $branches;
    private Milestone $milestone;
    private SemVerVersion $releaseVersion;
    private SecretKeyId $secretKeyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment       = $this->createMock(Variables::class);
        $this->loadEvent         = $this->createMock(LoadCurrentGithubEvent::class);
        $this->fetch             = $this->createMock(Fetch::class);
        $this->getMergeTargets   = $this->createMock(GetMergeTargetCandidateBranches::class);
        $this->getMilestone      = $this->createMock(GetGithubMilestone::class);
        $this->commitChangelog   = $this->createMock(CommitReleaseChangelog::class);
        $this->createReleaseText = $this->createMock(CreateReleaseText::class);
        $this->createTag         = $this->createMock(CreateTag::class);
        $this->push              = $this->createMock(Push::class);
        $this->createRelease     = $this->createMock(CreateRelease::class);
        $this->publishTweet      = $this->createMock(Publish::class);

        $this->command = new ReleaseCommand(
            $this->environment,
            $this->loadEvent,
            $this->fetch,
            $this->getMergeTargets,
            $this->getMilestone,
            $this->commitChangelog,
            $this->createReleaseText,
            $this->createTag,
            $this->push,
            $this->createRelease,
            $this->publishTweet
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
            'title'        => '1.2.3',
            'description'  => 'The description',
            'issues'       => [
                'nodes' => [],
            ],
            'pullRequests' => [
                'nodes' => [],
            ],
            'url'          => 'https://github.com/vendor/project/releases/milestone/123',
        ]);

        $this->releaseVersion = SemVerVersion::fromMilestoneName('1.2.3');
        $this->secretKeyId    = SecretKeyId::fromBase16String('aabbccddeeff');

        $this->environment->method('secretKeyId')
            ->willReturn($this->secretKeyId);
        $this->environment->method('githubToken')
            ->willReturn('github-auth-token');
    }

    public function testCommandName(): void
    {
        self::assertSame('laminas:automatic-releases:release', $this->command->getName());
    }

    /**
     * @throws Exception
     */
    public function testWillRelease(): void
    {
        $workspace = create_temporary_file(temp_dir(), 'workspace');

        delete_file($workspace);
        create_directory($workspace);
        create_directory($workspace . '/.git');

        $this->environment->method('githubWorkspacePath')
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
                self::equalTo($this->secretKeyId)
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

        $this->publishTweet->expects(self::once())
            ->method('__invoke')
            ->with(Tweet::fromMilestone($this->milestone));

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }
}
