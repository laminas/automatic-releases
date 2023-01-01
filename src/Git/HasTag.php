<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

interface HasTag
{
    /** @param non-empty-string $repositoryDirectory */
    public function __invoke(
        string $repositoryDirectory,
        string $tagName,
    ): bool;
}
