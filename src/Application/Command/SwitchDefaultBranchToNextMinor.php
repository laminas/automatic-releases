<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranch;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Psl\Filesystem\is_directory;
use function Psl\invariant;

final class SwitchDefaultBranchToNextMinor extends Command
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadGithubEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeCandidates;
    private Push $push;
    private SetDefaultBranch $switchDefaultBranch;
    private BumpAndCommitChangelogVersion $bumpChangelogVersion;

    public function __construct(
        Variables $environment,
        LoadCurrentGithubEvent $loadGithubEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeCandidates,
        Push $push,
        SetDefaultBranch $switchDefaultBranch,
        BumpAndCommitChangelogVersion $bumpChangelogVersion
    ) {
        parent::__construct('laminas:automatic-releases:switch-default-branch-to-next-minor');

        $this->environment          = $environment;
        $this->loadGithubEvent      = $loadGithubEvent;
        $this->fetch                = $fetch;
        $this->getMergeCandidates   = $getMergeCandidates;
        $this->push                 = $push;
        $this->switchDefaultBranch  = $switchDefaultBranch;
        $this->bumpChangelogVersion = $bumpChangelogVersion;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $event          = ($this->loadGithubEvent)();
        $repositoryPath = $this->environment->githubWorkspacePath();

        invariant(
            is_directory($repositoryPath . '/.git'),
            'Workspace is not a GIT repository.'
        );

        ($this->fetch)(
            $event->repository()
                ->uriWithTokenAuthentication($this->environment->githubToken()),
            $repositoryPath
        );

        $mergeCandidates = ($this->getMergeCandidates)($repositoryPath);
        $releaseVersion  = $event->version();
        $newestBranch    = $mergeCandidates->newestReleaseBranch();

        if ($newestBranch === null) {
            $output->writeln('No stable branches found: cannot switch default branch');

            return 0;
        }

        $nextDefaultBranch = $mergeCandidates->newestFutureReleaseBranchAfter($releaseVersion);

        if (! $mergeCandidates->contains($nextDefaultBranch)) {
            ($this->push)(
                $repositoryPath,
                $newestBranch->name(),
                $nextDefaultBranch->name()
            );
            ($this->bumpChangelogVersion)(
                BumpAndCommitChangelogVersion::BUMP_MINOR,
                $repositoryPath,
                $releaseVersion,
                $nextDefaultBranch,
                $this->environment->secretKeyId()
            );
        }

        ($this->switchDefaultBranch)($event->repository(), $nextDefaultBranch);

        return 0;
    }
}
