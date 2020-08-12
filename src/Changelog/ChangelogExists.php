<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;

interface ChangelogExists
{
    /**
     * @param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool;
}
