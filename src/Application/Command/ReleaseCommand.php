<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelog;
use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\CreateTag;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateRelease;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Psl;
use Psl\Filesystem;
use Psl\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReleaseCommand extends Command
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeTargets;
    private GetGithubMilestone $getMilestone;
    private CommitReleaseChangelog $commitChangelog;
    private CreateReleaseText $createChangelogText;
    private CreateTag $createTag;
    private Push $push;
    private CreateRelease $createRelease;

    public function __construct(
        Variables $environment,
        LoadCurrentGithubEvent $loadEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeTargets,
        GetGithubMilestone $getMilestone,
        CommitReleaseChangelog $commitChangelog,
        CreateReleaseText $createChangelogText,
        CreateTag $createTag,
        Push $push,
        CreateRelease $createRelease
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
