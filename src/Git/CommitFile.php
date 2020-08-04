<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

interface CommitFile
{
    public function __invoke(
        string $repositoryDirectory,
        string $filename,
        string $commitMessage
    ): void;
}
