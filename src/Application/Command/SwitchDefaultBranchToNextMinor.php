<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersionInterface;
use Laminas\AutomaticReleases\Environment\VariablesInterface;
use Laminas\AutomaticReleases\Git\FetchInterface;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesInterface;
use Laminas\AutomaticReleases\Git\PushInterface;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Psl;
use Psl\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SwitchDefaultBranchToNextMinor extends Command
{
    private VariablesInterface $variables;
    private LoadCurrentGithubEventInterface $loadGithubEvent;
    private FetchInterface $fetch;
    private GetMergeTargetCandidateBranchesInterface $getMergeCandidates;
    private PushInterface $push;
    private SetDefaultBranchInterface $switchDefaultBranch;
    private BumpAndCommitChangelogVersionInterface $bumpChangelogVersion;

    public function __construct(
        VariablesInterface $variables,
        LoadCurrentGithubEventInterface $loadGithubEvent,
        FetchInterface $fetch,
        GetMergeTargetCandidateBranchesInterface $getMergeCandidates,
        PushInterface $push,
        SetDefaultBranchInterface $switchDefaultBranch,
        BumpAndCommitChangelogVersionInterface $bumpChangelogVersion
    ) {
        parent::__construct('laminas:automatic-releases:switch-default-branch-to-next-minor');

        $this->variables            = $variables;
        $this->loadGithubEvent      = $loadGithubEvent;
        $this->fetch                = $fetch;
        $this->getMergeCandidates   = $getMergeCandidates;
        $this->push                 = $push;
        $this->switchDefaultBranch  = $switchDefaultBranch;
        $this->bumpChangelogVersion = $bumpChangelogVersion;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $event          = $this->loadGithubEvent->__invoke();
        $repositoryPath = $this->variables->githubWorkspacePath();

        Psl\invariant(Filesystem\is_directory($repositoryPath . '/.git'), 'Workspace is not a GIT repository.');

        $this->fetch->__invoke(
            $event->repository()
                ->uriWithTokenAuthentication($this->variables->githubToken()),
            $repositoryPath
        );

        $mergeCandidates = $this->getMergeCandidates->__invoke($repositoryPath);
        $releaseVersion  = $event->version();
        $newestBranch    = $mergeCandidates->newestReleaseBranch();

        if ($newestBranch === null) {
            $output->writeln('No stable branches found: cannot switch default branch');

            return 0;
        }

        $nextDefaultBranch = $mergeCandidates->newestFutureReleaseBranchAfter($releaseVersion);

        if (! $mergeCandidates->contains($nextDefaultBranch)) {
            $this->push->__invoke(
                $repositoryPath,
                $newestBranch->name(),
                $nextDefaultBranch->name()
            );
            ($this->bumpChangelogVersion)(
                BumpAndCommitChangelogVersionInterface::BUMP_MINOR,
                $repositoryPath,
                $releaseVersion,
                $nextDefaultBranch,
                $this->variables->signingSecretKey()
            );
        }

        $this->switchDefaultBranch->__invoke($event->repository(), $nextDefaultBranch);

        return 0;
    }
}
