<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;

interface GetMergeTargetCandidateBranches
{
    /** @psalm-param non-empty-string $repositoryRootDirectory */
    public function __invoke(string $repositoryRootDirectory): MergeTargetCandidateBranches;
}
