<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranch;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Psl;
use Psl\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SwitchDefaultBranchToNextMinor extends Command
{
    public function __construct(
        private readonly Variables $variables,
        private readonly LoadCurrentGithubEvent $loadGithubEvent,
        private readonly Fetch $fetch,
        private readonly GetMergeTargetCandidateBranches $getMergeCandidates,
        private readonly Push $push,
        private readonly SetDefaultBranch $switchDefaultBranch,
        private readonly BumpAndCommitChangelogVersion $bumpChangelogVersion,
    ) {
        parent::__construct('laminas:automatic-releases:switch-default-branch-to-next-minor');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $event          = $this->loadGithubEvent->__invoke();
        $repositoryPath = $this->variables->githubWorkspacePath();
        $repositoryName = $event->repository();

        Psl\invariant(Filesystem\is_directory($repositoryPath . '/.git'), 'Workspace is not a GIT repository.');

        $this->fetch->__invoke(
            $repositoryName->uri(),
            $repositoryName->uriWithTokenAuthentication($this->variables->githubToken()),
            $repositoryPath,
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
            $baseBranch = $mergeCandidates->targetBranchFor($releaseVersion);
            if ($baseBranch === null) {
                $output->writeln('Target branch for release was not found');

                return 1;
            }

            $this->push->__invoke(
                $repositoryPath,
                $baseBranch->name(),
                $nextDefaultBranch->name(),
            );
            ($this->bumpChangelogVersion)(
                BumpAndCommitChangelogVersion::BUMP_MINOR,
                $repositoryPath,
                $releaseVersion,
                $nextDefaultBranch,
                $this->variables->signingSecretKey(),
            );
        }

        $this->switchDefaultBranch->__invoke($event->repository(), $nextDefaultBranch);

        return 0;
    }
}
