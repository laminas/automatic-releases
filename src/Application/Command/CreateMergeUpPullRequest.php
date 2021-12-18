<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Psl\Filesystem\is_directory;
use function Psl\invariant;
use function Psl\SecureRandom\string;
use function Psl\Str\format;

final class CreateMergeUpPullRequest extends Command
{
    private Variables $environment;
    private LoadCurrentGithubEvent $loadGithubEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeCandidates;
    private GetGithubMilestone $getMilestone;
    private CreateReleaseText $createReleaseText;
    private Push $push;
    private CreatePullRequest $createPullRequest;

    public function __construct(
        Variables $environment,
        LoadCurrentGithubEvent $loadGithubEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeCandidates,
        GetGithubMilestone $getMilestone,
        CreateReleaseText $createReleaseText,
        Push $push,
        CreatePullRequest $createPullRequest
    ) {
        parent::__construct('laminas:automatic-releases:create-merge-up-pull-request');

        $this->environment        = $environment;
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
        $event          = ($this->loadGithubEvent)();
        $repositoryPath = $this->environment->githubWorkspacePath();

        invariant(is_directory($repositoryPath . '/.git'), 'Workspace is not a GIT repository.');

        ($this->fetch)(
            $event->repository()
                ->uriWithTokenAuthentication($this->environment->githubToken()),
            $repositoryPath
        );

        $mergeCandidates = ($this->getMergeCandidates)($repositoryPath);

        $releaseVersion = $event->version();
        $releaseBranch  = $mergeCandidates->targetBranchFor($releaseVersion);
        $mergeUpTarget  = $mergeCandidates->branchToMergeUp($releaseVersion);

        if ($mergeUpTarget === null) {
            $output->writeln(format(
                'No merge-up candidate for release %s - skipping pull request creation',
                $releaseVersion->fullReleaseName()
            ));

            return 0;
        }

        invariant($releaseBranch !== null, format('No valid release branch found for version %s', $releaseVersion->fullReleaseName()));

        $mergeUpBranch = BranchName::fromName(
            $releaseBranch->name()
            . '-merge-up-into-'
            . $mergeUpTarget->name()
            . '_'
            . string(8) // This is to ensure that a new merge-up pull request is created even if one already exists
        );

        $releaseNotes = ($this->createReleaseText)(
            ($this->getMilestone)($event->repository(), $event->milestoneNumber()),
            $event->repository(),
            $event->version(),
            $releaseBranch,
            $repositoryPath
        );

        ($this->push)($repositoryPath, $releaseBranch->name(), $mergeUpBranch->name());
        ($this->createPullRequest)(
            $event->repository(),
            $mergeUpBranch,
            $mergeUpTarget,
            'Merge release ' . $releaseVersion->fullReleaseName() . ' into ' . $mergeUpTarget->name(),
            $releaseNotes->contents()
        );

        return 0;
    }
}
