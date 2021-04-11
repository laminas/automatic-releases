<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Twitter;

use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
final class CreateTweetThroughApiCall
{
    public function __invoke(MilestoneClosedEvent $event): UriInterface
    {
    }
}
