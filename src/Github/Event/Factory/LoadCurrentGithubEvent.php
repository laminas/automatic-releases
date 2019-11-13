<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Event;

interface LoadCurrentGithubEvent
{
    public function __invoke() : ?MilestoneClosedEvent;
}
