<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

interface CommitReleaseChangelog
{
    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        ChangelogReleaseNotes $releaseNotes,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch,
        SecretKeyId $keyId
    ): void;
}
