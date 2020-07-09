<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\V3;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;

interface CreatePullRequest
{
    public function __invoke(
        RepositoryName $repository,
        BranchName $head,
        BranchName $target,
        string $title,
        string $body
    ) : void;
}
