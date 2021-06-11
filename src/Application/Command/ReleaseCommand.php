<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelogInterface;
use Laminas\AutomaticReleases\Environment\VariablesInterface;
use Laminas\AutomaticReleases\Git\CreateTagInterface;
use Laminas\AutomaticReleases\Git\FetchInterface;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesInterface;
use Laminas\AutomaticReleases\Git\PushInterface;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestoneInterface;
use Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseInterface;
use Laminas\AutomaticReleases\Github\CreateReleaseTextInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Psl;
use Psl\Filesystem;
use Psl\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReleaseCommand extends Command
{
    private VariablesInterface $environment;
    private LoadCurrentGithubEventInterface $loadEvent;
    private FetchInterface $fetch;
    private GetMergeTargetCandidateBranchesInterface $getMergeTargets;
    private GetGithubMilestoneInterface $getMilestone;
    private CommitReleaseChangelogInterface $commitChangelog;
    private CreateReleaseTextInterface $createChangelogText;
    private CreateTagInterface $createTag;
    private PushInterface $push;
    private CreateReleaseInterface $createRelease;

    public function __construct(
        VariablesInterface $environment,
        LoadCurrentGithubEventInterface $loadEvent,
        FetchInterface $fetch,
        GetMergeTargetCandidateBranchesInterface $getMergeTargets,
        GetGithubMilestoneInterface $getMilestone,
        CommitReleaseChangelogInterface $commitChangelog,
        CreateReleaseTextInterface $createChangelogText,
        CreateTagInterface $createTag,
        PushInterface $push,
        CreateReleaseInterface $createRelease
    ) {
        parent::__construct('laminas:automatic-releases:release');

        $this->environment         = $environment;
        $this->loadEvent           = $loadEvent;
        $this->fetch               = $fetch;
        $this->getMergeTargets     = $getMergeTargets;
        $this->getMilestone        = $getMilestone;
        $this->commitChangelog     = $commitChangelog;
        $this->createChangelogText = $createChangelogText;
        $this->createTag           = $createTag;
        $this->push                = $push;
        $this->createRelease       = $createRelease;
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
        $milestone       = ($this->getMilestone)($repositoryName, $milestoneClosedEvent->milestoneNumber());

        /** @psalm-suppress UnusedMethodCall */
        $milestone->assertAllIssuesAreClosed();

        $releaseBranch = $mergeCandidates->targetBranchFor($releaseVersion);

        Psl\invariant($releaseBranch !== null, Str\format('No valid release branch found for version %s', $releaseVersion->fullReleaseName()));

        $changelogReleaseNotes = ($this->createChangelogText)(
            $milestone,
            $repositoryName,
            $releaseVersion,
            $releaseBranch,
            $repositoryPath
        );

        ($this->commitChangelog)(
            $changelogReleaseNotes,
            $repositoryPath,
            $releaseVersion,
            $releaseBranch,
            $this->environment->signingSecretKey()
        );

        $tagName = $releaseVersion->fullReleaseName();

        ($this->createTag)(
            $repositoryPath,
            $releaseBranch,
            $tagName,
            $changelogReleaseNotes->contents(),
            $this->environment->signingSecretKey()
        );
        ($this->push)($repositoryPath, $tagName);
        ($this->push)($repositoryPath, $tagName, $releaseVersion->targetReleaseBranchName()->name());
        ($this->createRelease)($repositoryName, $releaseVersion, $changelogReleaseNotes->contents());

        return 0;
    }
}
