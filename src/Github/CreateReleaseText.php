<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;

interface CreateReleaseText
{
    /** @psalm-return non-empty-string */
    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion
    ): string;
}
