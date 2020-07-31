<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

interface ReleaseChangelogAndFetchContents
{
    /**
     * @return string|null Null indicates failure to fetch changelog text
     */
    public function __invoke(ReleaseChangelogEvent $releaseChangelogEvent): ?string;
}
