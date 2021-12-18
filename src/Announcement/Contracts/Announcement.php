<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Announcement\Contracts;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;

interface Announcement
{
    public function __toString(): string;

    public static function fromMilestone(Milestone $milestone): self;
}
