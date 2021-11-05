<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event\Factory;

use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;

interface LoadCurrentGithubEventInterface
{
    public function __invoke(): MilestoneClosedEvent;
}
