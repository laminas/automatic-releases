<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;

interface GenerateChangelog
{
    public function __invoke(
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion
    ): string;
}
