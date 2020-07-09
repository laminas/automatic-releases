<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;

interface GetMergeTargetCandidateBranches
{
    /** @psalm-param non-empty-string $repositoryRootDirectory */
    public function __invoke(string $repositoryRootDirectory): MergeTargetCandidateBranches;
}
