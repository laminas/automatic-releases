<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

interface BumpAndCommitChangelogVersion
{
    public const BUMP_MINOR = 'bumpMinorVersion';
    public const BUMP_PATCH = 'bumpPatchVersion';

    /**
     * @psalm-param self::BUMP_*     $bumpType
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        string $bumpType,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch,
        SecretKeyId $keyId
    ): void;
}
