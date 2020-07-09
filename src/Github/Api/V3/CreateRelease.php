<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Message\UriInterface;

interface CreateRelease
{
    /** @psalm-param non-empty-string $releaseNotes */
    public function __invoke(
        RepositoryName $repository,
        SemVerVersion $version,
        string $releaseNotes
    ): UriInterface;
}
