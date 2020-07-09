<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;

interface GenerateChangelog
{
    public function __invoke(
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion
    ): string;
}
