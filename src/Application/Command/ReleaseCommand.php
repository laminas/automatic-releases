<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Application\Command;

use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Git\CreateTag;
use Doctrine\AutomaticReleases\Git\Fetch;
use Doctrine\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Doctrine\AutomaticReleases\Git\Push;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateRelease;
use Doctrine\AutomaticReleases\Github\CreateReleaseText;
use Doctrine\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function sprintf;

final class ReleaseCommand extends Command
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeTargets;
    private GetGithubMilestone $getMilestone;
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
        CreateReleaseText $createChangelogText,
        CreateTag $createTag,
        Push $push,
        CreateRelease $createRelease
    ) {
        parent::__construct('doctrine:automatic-releases:release');

        $this->environment         = $environment;
        $this->loadEvent           = $loadEvent;
        $this->fetch               = $fetch;
        $this->getMergeTargets     = $getMergeTargets;
        $this->getMilestone        = $getMilestone;
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

        Assert::directory($repositoryPath . '/.git');

        ($this->fetch)($repositoryCloneUri, $repositoryPath);

        $mergeCandidates = ($this->getMergeTargets)($repositoryPath);
        $releaseVersion  = $milestoneClosedEvent->version();
        $milestone       = ($this->getMilestone)($repositoryName, $milestoneClosedEvent->milestoneNumber());

        $milestone->assertAllIssuesAreClosed();

        $releaseBranch = $mergeCandidates->targetBranchFor($releaseVersion);

        Assert::notNull(
            $releaseBranch,
            sprintf('No valid release branch found for version %s', $releaseVersion->fullReleaseName())
        );

        $changelog = ($this->createChangelogText)($milestone, $milestoneClosedEvent->repository(), $releaseVersion);

        $tagName = $releaseVersion->fullReleaseName();

        ($this->createTag)($repositoryPath, $releaseBranch, $tagName, $changelog, $this->environment->signingSecretKey());
        ($this->push)($repositoryPath, $tagName);
        ($this->push)($repositoryPath, $tagName, $releaseBranch->name());
        ($this->createRelease)($repositoryName, $releaseVersion, $changelog);

        return 0;
    }
}
