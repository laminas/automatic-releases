<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;

interface CreateTag
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId
    ): void;
}
