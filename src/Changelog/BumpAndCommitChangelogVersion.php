<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;

interface BumpAndCommitChangelogVersion
{
    public const BUMP_MINOR = 'bumpMinorVersion';
    public const BUMP_PATCH = 'bumpPatchVersion';

    public const KNOWN_BUMP_TYPES = [
        self::BUMP_MINOR,
        self::BUMP_PATCH,
    ];

    /**
     * @psalm-param value-of<self::KNOWN_BUMP_TYPES> $bumpType
     * @psalm-param non-empty-string                 $repositoryDirectory
     */
    public function __invoke(
        string $bumpType,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch
    ): void;
}
