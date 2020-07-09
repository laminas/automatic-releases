<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

interface Push
{
    /**
     * @psalm-param non-empty-string      $repositoryDirectory
     * @psalm-param non-empty-string      $symbol
     * @psalm-param non-empty-string|null $alias
     */
    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ): void;
}
