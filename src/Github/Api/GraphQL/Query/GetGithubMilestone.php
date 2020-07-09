<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;

interface GetGithubMilestone
{
    public function __invoke(
        RepositoryName $repositoryName,
        int $milestoneNumber
    ): Milestone;
}
