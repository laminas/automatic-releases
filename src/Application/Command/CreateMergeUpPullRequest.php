<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Environment\VariablesInterface;
use Laminas\AutomaticReleases\Git\FetchInterface;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesInterface;
use Laminas\AutomaticReleases\Git\PushInterface;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestoneInterface;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestInterface;
use Laminas\AutomaticReleases\Github\CreateReleaseTextInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Psl;
use Psl\Filesystem;
use Psl\SecureRandom;
use Psl\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateMergeUpPullRequest extends Command
{
    private VariablesInterface $variables;
    private LoadCurrentGithubEventInterface $loadGithubEvent;
    private FetchInterface $fetch;
    private GetMergeTargetCandidateBranchesInterface $getMergeCandidates;
    private GetGithubMilestoneInterface $getMilestone;
    private CreateReleaseTextInterface $createReleaseText;
    private PushInterface $push;
    private CreatePullRequestInterface $createPullRequest;

    public function __construct(
        VariablesInterface $variables,
        LoadCurrentGithubEventInterface $loadGithubEvent,
        FetchInterface $fetch,
        GetMergeTargetCandidateBranchesInterface $getMergeCandidates,
        GetGithubMilestoneInterface $getMilestone,
        CreateReleaseTextInterface $createReleaseText,
        PushInterface $push,
        CreatePullRequestInterface $createPullRequest
    ) {
        parent::__construct('laminas:automatic-releases:create-merge-up-pull-request');

        $this->variables          = $variables;
        $this->loadGithubEvent    = $loadGithubEvent;
        $this->fetch              = $fetch;
        $this->getMergeCandidates = $getMergeCandidates;
        $this->getMilestone       = $getMilestone;
        $this->createReleaseText  = $createReleaseText;
        $this->push               = $push;
        $this->createPullRequest  = $createPullRequest;
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

        $releaseVersion = $event->version();
        $releaseBranch  = $mergeCandidates->targetBranchFor($releaseVersion);
        $mergeUpTarget  = $mergeCandidates->branchToMergeUp($releaseVersion);

        if ($mergeUpTarget === null) {
            $output->writeln(Str\format(
                'No merge-up candidate for release %s - skipping pull request creation',
                $releaseVersion->fullReleaseName()
            ));

            return 0;
        }

        Psl\invariant($releaseBranch !== null, Str\format('No valid release branch found for version %s', $releaseVersion->fullReleaseName()));

        $mergeUpBranch = BranchName::fromName(
            $releaseBranch->name()
            . '-merge-up-into-'
            . $mergeUpTarget->name()
            . '_'
            . SecureRandom\string(8) // This is to ensure that a new merge-up pull request is created even if one already exists
        );

        $releaseNotes = $this->createReleaseText->__invoke(
            $this->getMilestone->__invoke($event->repository(), $event->milestoneNumber()),
            $event->repository(),
            $event->version(),
            $releaseBranch,
            $repositoryPath
        );

        $this->push->__invoke($repositoryPath, $releaseBranch->name(), $mergeUpBranch->name());
        $this->createPullRequest->__invoke(
            $event->repository(),
            $mergeUpBranch,
            $mergeUpTarget,
            'Merge release ' . $releaseVersion->fullReleaseName() . ' into ' . $mergeUpTarget->name(),
            $releaseNotes->contents()
        );

        return 0;
    }
}
