<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;

interface CommitFile
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $filename,
        string $commitMessage
    ): void;
}
