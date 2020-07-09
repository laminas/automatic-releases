<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Event\Factory;

use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;

interface LoadCurrentGithubEvent
{
    public function __invoke() : MilestoneClosedEvent;
}
