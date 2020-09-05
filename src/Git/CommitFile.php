<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

interface CommitFile
{
    /**
     * @psalm-param non-empty-string $repositoryDirectory
     * @psalm-param non-empty-string $filename
     * @psalm-param non-empty-string $commitMessage
     */
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $filename,
        string $commitMessage,
        SecretKeyId $keyId
    ): void;
}
