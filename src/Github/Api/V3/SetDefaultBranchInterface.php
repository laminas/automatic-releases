<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;

interface SetDefaultBranchInterface
{
    /** @psalm-param non-empty-string $title */
    public function __invoke(
        RepositoryName $repository,
        BranchName $defaultBranch
    ): void;
}
