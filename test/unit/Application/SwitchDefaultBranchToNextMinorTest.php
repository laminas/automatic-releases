<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\SwitchDefaultBranchToNextMinor;
use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranch;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

use function mkdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class SwitchDefaultBranchToNextMinorTest extends TestCase
{
    /** @var Variables&MockObject */
    private Variables $variables;
    /** @var LoadCurrentGithubEvent&MockObject */
    private LoadCurrentGithubEvent $loadEvent;
    /** @var Fetch&MockObject */
    private Fetch $fetch;
    /** @var GetMergeTargetCandidateBranches&MockObject */
    private GetMergeTargetCandidateBranches $getMergeTargets;
    /** @var Push&MockObject */
    private Push $push;
    /** @var SetDefaultBranch&MockObject */
    private SetDefaultBranch $setDefaultBranch;
    /** @var BumpAndCommitChangelogVersion&MockObject */
    private BumpAndCommitChangelogVersion $bumpChangelogVersion;
    private SwitchDefaultBranchToNextMinor $command;
    private MilestoneClosedEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables            = $this->createMock(Variables::class);
        $this->loadEvent            = $this->createMock(LoadCurrentGithubEvent::class);
        $this->fetch                = $this->createMock(Fetch::class);
        $this->getMergeTargets      = $this->createMock(GetMergeTargetCandidateBranches::class);
        $this->push                 = $this->createMock(Push::class);
        $this->setDefaultBranch     = $this->createMock(SetDefaultBranch::class);
        $this->bumpChangelogVersion = $this->createMock(BumpAndCommitChangelogVersion::class);

        $this->command = new SwitchDefaultBranchToNextMinor(
            $this->variables,
            $this->loadEvent,
            $this->fetch,
            $this->getMergeTargets,
            $this->push,
            $this->setDefaultBranch,
            $this->bumpChangelogVersion
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

        $this->variables->method('githubToken')
            ->willReturn('github-auth-token');
    }

    public function testCommandName(): void
    {
        self::assertSame('laminas:automatic-releases:switch-default-branch-to-next-minor', $this->command->getName());
    }

    public function testWillSwitchToExistingNewestDefaultBranch(): void
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
                BranchName::fromName('1.3.x'),
                BranchName::fromName('master'),
            ));

        $this->push->expects(self::never())
            ->method('__invoke');

        $this->bumpChangelogVersion->expects(self::never())
            ->method('__invoke');

        $this->setDefaultBranch->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::equalTo(BranchName::fromName('1.3.x'))
            );

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function testWillSwitchToNewlyCreatedDefaultBranchWhenNoNewerReleaseBranchExists(): void
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
                BranchName::fromName('master'),
            ));

        $this->push->expects(self::once())
            ->method('__invoke')
            ->with($workspace, '1.2.x', '1.3.x');

        $this->bumpChangelogVersion->expects(self::once())
            ->method('__invoke')
            ->with(
                BumpAndCommitChangelogVersion::BUMP_MINOR,
                $workspace,
                SemVerVersion::fromMilestoneName('1.2.3'),
                BranchName::fromName('1.3.x')
            );

        $this->setDefaultBranch->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo(RepositoryName::fromFullName('foo/bar')),
                self::equalTo(BranchName::fromName('1.3.x'))
            );

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function testWillNotSwitchDefaultBranchIfNoBranchesExist(): void
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
                BranchName::fromName('master'),
            ));

        $this->push->expects(self::never())
            ->method('__invoke');

        $this->bumpChangelogVersion->expects(self::never())
            ->method('__invoke');

        $this->setDefaultBranch->expects(self::never())
            ->method('__invoke');

        $output = new BufferedOutput();

        self::assertSame(0, $this->command->run(new ArrayInput([]), $output));

        self::assertSame(
            <<<OUTPUT
No stable branches found: cannot switch default branch

OUTPUT
            ,
            $output->fetch()
        );
    }
}
