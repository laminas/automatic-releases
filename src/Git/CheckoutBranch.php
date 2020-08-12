<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;

interface CheckoutBranch
{
    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        string $repositoryDirectory,
        BranchName $branchName
    ): void;
}
