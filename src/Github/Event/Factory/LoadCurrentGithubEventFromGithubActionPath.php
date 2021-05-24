<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event\Factory;

use Laminas\AutomaticReleases\Environment\Contracts\GithubVariablesInterface;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Psl\Filesystem;

final class LoadCurrentGithubEventFromGithubActionPath implements LoadCurrentGithubEvent
{
    private GithubVariablesInterface $githubEnvironmentVariables;

    public function __construct(GithubVariablesInterface $githubEnvironmentVariables)
    {
        $this->githubEnvironmentVariables = $githubEnvironmentVariables;
    }

    public function __invoke(): MilestoneClosedEvent
    {
        $path = $this->githubEnvironmentVariables->eventPath();

        return MilestoneClosedEvent::fromEventJson(Filesystem\read_file($path));
    }
}
