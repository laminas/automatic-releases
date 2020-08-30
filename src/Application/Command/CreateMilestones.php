<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneFailed;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateMilestones extends Command
{
    private LoadCurrentGithubEvent $loadEvent;
    private GetGithubMilestone $getMilestone;
    private CreateMilestone $createMilestone;

    public function __construct(
        LoadCurrentGithubEvent $loadEvent,
        GetGithubMilestone $getMilestone,
        CreateMilestone $createMilestone
    ) {
        parent::__construct('laminas:automatic-releases:create-milestones');

        $this->loadEvent       = $loadEvent;
        $this->getMilestone    = $getMilestone;
        $this->createMilestone = $createMilestone;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $milestoneClosedEvent = ($this->loadEvent)();
        $repositoryName       = $milestoneClosedEvent->repository();
        $releaseVersion       = $milestoneClosedEvent->version();
        $milestone            = ($this->getMilestone)($repositoryName, $milestoneClosedEvent->milestoneNumber());

        $milestone->assertAllIssuesAreClosed();

        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextPatch(), $output);
        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextMinor(), $output);
        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextMajor(), $output);

        return 0;
    }

    private function createMilestoneIfNotExists(RepositoryName $repositoryName, SemVerVersion $version, OutputInterface $output): bool
    {
        try {
            ($this->createMilestone)($repositoryName, $version->nextPatch());
        } catch (CreateMilestoneFailed $e) {
            $output->writeln($e->getMessage());
            return false;
        }

        return true;
    }
}
