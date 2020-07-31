<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

interface ReleaseChangelogAndFetchContents
{
    /**
     * @return null|string Null indicates failure to fetch changelog text
     */
    public function __invoke(ReleaseChangelogEvent $releaseChangelogEvent): ?string;
}
