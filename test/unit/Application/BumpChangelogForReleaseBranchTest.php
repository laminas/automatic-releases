<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\BumpChangelogForReleaseBranch;
use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;
use function Psl\Filesystem\read_file;

class BumpChangelogForReleaseBranchTest extends TestCase
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeTargets;
    private BumpAndCommitChangelogVersion $bumpChangelogVersion;
    private MilestoneClosedEvent $event;
    private BumpChangelogForReleaseBranch $command;

    protected function setUp(): void
    {
        $this->environment          = $this->createMock(Variables::class);
        $this->loadEvent            = $this->createMock(LoadCurrentGithubEvent::class);
        $this->fetch                = $this->createMock(Fetch::class);
        $this->getMergeTargets      = $this->createMock(GetMergeTargetCandidateBranches::class);
        $this->bumpChangelogVersion = $this->createMock(BumpAndCommitChangelogVersion::class);

        $this->command = new BumpChangelogForReleaseBranch(
            $this->environment,
            $this->loadEvent,
            $this->fetch,
            $this->getMergeTargets,
            $this->bumpChangelogVersion
        );

        $this->event = MilestoneClosedEvent::fromEventJson(<<< 'JSON'
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
            JSON);

        $key = (new ImportGpgKeyFromStringViaTemporaryFile())(read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc'));

        $this->environment->method('secretKeyId')->willReturn($key);
    }

    public function testWillBumpChangelogVersion(): void
    {
        $workspace = create_temporary_file(temp_dir(), 'workspace');

        delete_file($workspace);
        create_directory($workspace);
        create_directory($workspace . '/.git');

        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1.x'),
            BranchName::fromName('1.2.x'),
            BranchName::fromName('1.3.x')
        );

        $this->loadEvent->method('__invoke')->willReturn($this->event);
        $this->environment->method('githubToken')->willReturn('github-auth-token');
        $this->environment->method('githubWorkspacePath')->willReturn($workspace);
        $this->fetch->expects(self::once())
            ->method('__invoke')
            ->with(
                'https://github-auth-token:x-oauth-basic@github.com/foo/bar.git',
                $workspace
            );
        $this->getMergeTargets->expects(self::once())
            ->method('__invoke')
            ->with($workspace)
            ->willReturn($branches);

        $this->bumpChangelogVersion->expects(self::once())
            ->method('__invoke')
            ->with(
                BumpAndCommitChangelogVersion::BUMP_PATCH,
                $workspace,
                self::equalTo(SemVerVersion::fromMilestoneName('1.2.3')),
                self::equalTo(BranchName::fromName('1.2.x'))
            );

        self::assertSame(0, $this->command->run(new ArrayInput([]), new NullOutput()));
    }
}
