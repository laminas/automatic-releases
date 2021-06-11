<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneFailedException;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneInterface;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventInterface;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateMilestones extends Command
{
    private LoadCurrentGithubEventInterface $loadEvent;
    private CreateMilestoneInterface $createMilestone;

    public function __construct(
        LoadCurrentGithubEventInterface $loadEvent,
        CreateMilestoneInterface $createMilestone
    ) {
        parent::__construct('laminas:automatic-releases:create-milestones');

        $this->loadEvent       = $loadEvent;
        $this->createMilestone = $createMilestone;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $milestoneClosedEvent = ($this->loadEvent)();
        $repositoryName       = $milestoneClosedEvent->repository();
        $releaseVersion       = $milestoneClosedEvent->version();

        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextPatch());
        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextMinor());
        $this->createMilestoneIfNotExists($repositoryName, $releaseVersion->nextMajor());

        return 0;
    }

    private function createMilestoneIfNotExists(RepositoryName $repositoryName, SemVerVersion $version): void
    {
        try {
            ($this->createMilestone)($repositoryName, $version);
        } catch (CreateMilestoneFailedException) {
            return;
        }
    }
}
