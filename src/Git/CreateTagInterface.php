<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

interface CreateTagInterface
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId
    ): void;
}
