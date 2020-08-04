<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;

interface ReleaseChangelog
{
    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch
    ): void;
}
