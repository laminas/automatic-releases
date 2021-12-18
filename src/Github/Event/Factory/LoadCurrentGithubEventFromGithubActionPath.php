<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event\Factory;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;

use function Psl\Filesystem\read_file;

final class LoadCurrentGithubEventFromGithubActionPath implements LoadCurrentGithubEvent
{
    private Variables $environment;

    public function __construct(Variables $environment)
    {
        $this->environment = $environment;
    }

    public function __invoke(): MilestoneClosedEvent
    {
        $path = $this->environment->githubEventPath();

        return MilestoneClosedEvent::fromEventJson(read_file($path));
    }
}
