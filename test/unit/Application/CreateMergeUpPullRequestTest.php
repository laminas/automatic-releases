<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\CreateMergeUpPullRequest;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

use function mkdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class CreateMergeUpPullRequestTest extends TestCase
{
    /** @var Variables&MockObject */
    private Variables $variables;
    /** @var LoadCurrentGithubEvent&MockObject */
    private LoadCurrentGithubEvent $loadEvent;
    /** @var Fetch&MockObject */
    private Fetch $fetch;
    /** @var GetMergeTargetCandidateBranches&MockObject */
    private GetMergeTargetCandidateBranches $getMergeTargets;
    /** @var GetGithubMilestone&MockObject */
    private GetGithubMilestone $getMilestone;
    /** @var CreateReleaseText&MockObject */
    private CreateReleaseText $createReleaseText;
    /** @var Push&MockObject */
    private Push $push;
    /** @var CreatePullRequest&MockObject */
    private CreatePullRequest $createPullRequest;
    private CreateMergeUpPullRequest $command;
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
        $this->createReleaseText = $this->createMock(CreateReleaseText::class);
        $this->push              = $this->createMock(Push::class);
        $this->createPullRequest = $this->createMock(CreatePullRequest::class);

        $this->command = new CreateMergeUpPullRequest(
            $this->variables,
            $this->loadEvent,
            $this->fetch,
            $this->getMergeTargets,
            $this->getMilestone,
            $this->createReleaseText,
            $this->push,
            $this->createPullRequest
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
            'url'          => 'http://example.com/milestone',
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
        self::assertSame('laminas:automatic-releases:create-merge-up-pull-request', $this->command->getName());
    }

    public function testWillCreateMergeUpPullRequest(): void
    {
        $workspace = tempnam(sys_get_temp_dir(), 'workspace');

        unlink($workspace);
        mkdir($workspace);
        mkdir($workspace . '/.git');

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

        $this->createReleaseText->method('__invoke')
            ->with(
                self::equalTo($this->milestone),
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::equalTo($this->releaseVersion)
            )
            ->willReturn('text of the changelog');

        $this->push->expects(self::once())
            ->method('__invoke')
            ->with(
                $workspace,
                '1.2.x',
                self::stringStartsWith('1.2.x-merge-up-into-1.3.x_')
            );

        $this->createPullRequest->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::callback(static function (BranchName $branch): bool {
                    self::assertStringStartsWith('1.2.x-merge-up-into-1.3.x_', $branch->name());

                    return true;
                }),
                self::equalTo(BranchName::fromName('1.3.x')),
                'Merge release 1.2.3 into 1.3.x',
                'text of the changelog'
            );

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function testWillSkipMergeUpPullRequestOnNoMergeUpCandidate(): void
    {
        $workspace = tempnam(sys_get_temp_dir(), 'workspace');

        unlink($workspace);
        mkdir($workspace);
        mkdir($workspace . '/.git');

        $this->variables->method('githubWorkspacePath')
            ->willReturn($workspace);

        $this->loadEvent->method('__invoke')
            ->willReturn($this->event);

        $this->fetch->expects(self::once())
            ->method('__invoke')
            ->with('https://github-auth-token:x-oauth-basic@github.com/foo/bar.git', $workspace);

        $this->getMergeTargets->method('__invoke')
            ->with($workspace)
            ->willReturn(MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.1.x'),
                BranchName::fromName('1.2.x'),
            ));

        $this->getMilestone->method('__invoke')
            ->willReturn($this->milestone);

        $this->createReleaseText->method('__invoke')
            ->willReturn('text of the changelog');

        $this->push->expects(self::never())
            ->method('__invoke');

        $this->createPullRequest->expects(self::never())
            ->method('__invoke');

        $output = new BufferedOutput();

        self::assertSame(0, $this->command->run(new ArrayInput([]), $output));

        self::assertSame(
            <<<OUTPUT
No merge-up candidate for release 1.2.3 - skipping pull request creation

OUTPUT
            ,
            $output->fetch()
        );
    }
}
