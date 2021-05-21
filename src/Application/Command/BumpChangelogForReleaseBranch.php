<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Psl;
use Psl\Filesystem;
use Psl\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BumpChangelogForReleaseBranch extends Command
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeTargets;
    private BumpAndCommitChangelogVersion $bumpChangelogVersion;

    public function __construct(
        Variables $environment,
        LoadCurrentGithubEvent $loadEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeTargets,
        BumpAndCommitChangelogVersion $bumpChangelogVersion
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
            BumpAndCommitChangelogVersion::BUMP_PATCH,
            $repositoryPath,
            $releaseVersion,
            $releaseBranch,
            $this->environment->signingSecretKey()
        );

        return 0;
    }
}
