<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

interface CheckoutBranch
{
    /**
     * @psalm-param non-empty-string $repositoryDirectory
     * @psalm-param non-empty-string $branchName
     */
    public function __invoke(
        string $repositoryDirectory,
        string $branchName
    ): void;
}
