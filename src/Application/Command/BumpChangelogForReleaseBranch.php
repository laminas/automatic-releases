<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersionInterface;
use Laminas\AutomaticReleases\Environment\VariablesInterface;
use Laminas\AutomaticReleases\Git\FetchInterface;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Psl;
use Psl\Filesystem;
use Psl\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BumpChangelogForReleaseBranch extends Command
{
    private VariablesInterface $environment;
    private LoadCurrentGithubEventInterface $loadEvent;
    private FetchInterface $fetch;
    private GetMergeTargetCandidateBranchesInterface $getMergeTargets;
    private BumpAndCommitChangelogVersionInterface $bumpChangelogVersion;

    public function __construct(
        VariablesInterface $environment,
        LoadCurrentGithubEventInterface $loadEvent,
        FetchInterface $fetch,
        GetMergeTargetCandidateBranchesInterface $getMergeTargets,
        BumpAndCommitChangelogVersionInterface $bumpChangelogVersion
    ) {
        parent::__construct('laminas:automatic-releases:bump-changelog');

        $this->environment          = $environment;
        $this->loadEvent            = $loadEvent;
        $this->fetch                = $fetch;
        $this->getMergeTargets      = $getMergeTargets;
        $this->bumpChangelogVersion = $bumpChangelogVersion;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $milestoneClosedEvent = ($this->loadEvent)();
        $repositoryName       = $milestoneClosedEvent->repository();
        $repositoryCloneUri   = $repositoryName->uriWithTokenAuthentication($this->environment->githubToken());
        $repositoryPath       = $this->environment->githubWorkspacePath();

        Psl\invariant(Filesystem\is_directory($repositoryPath . '/.git'), 'Workspace is not a GIT repository.');

        ($this->fetch)($repositoryCloneUri, $repositoryPath);

        $mergeCandidates = ($this->getMergeTargets)($repositoryPath);
        $releaseVersion  = $milestoneClosedEvent->version();
        $releaseBranch   = $mergeCandidates->targetBranchFor($releaseVersion);

        Psl\invariant($releaseBranch !== null, Str\format('No valid release branch found for version %s', $releaseVersion->fullReleaseName()));

        ($this->bumpChangelogVersion)(
            BumpAndCommitChangelogVersionInterface::BUMP_PATCH,
            $repositoryPath,
            $releaseVersion,
            $releaseBranch,
            $this->environment->signingSecretKey()
        );

        return 0;
    }
}
