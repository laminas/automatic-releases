<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Psr\Http\Message\UriInterface;

interface Fetch
{
    /** @psalm-param non-empty-string $repositoryRootDirectory */
    public function __invoke(
        UriInterface $repositoryUri,
        string $repositoryRootDirectory
    ): void;
}
