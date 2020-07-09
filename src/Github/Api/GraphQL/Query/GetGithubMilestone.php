<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;

interface GetGithubMilestone
{
    public function __invoke(
        RepositoryName $repositoryName,
        int $milestoneNumber
    ): Milestone;
}
