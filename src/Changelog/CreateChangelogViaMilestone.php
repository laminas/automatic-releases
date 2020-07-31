<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Github\CreateReleaseText;

class CreateChangelogViaMilestone implements ReleaseChangelogAndFetchContents
{
    private CreateReleaseText $generateChangelog;

    public function __construct(CreateReleaseText $generateChangelog)
    {
        $this->generateChangelog = $generateChangelog;
    }

    public function __invoke(ReleaseChangelogEvent $releaseChangelogEvent): ?string
    {
        return ($this->generateChangelog)(
            $releaseChangelogEvent->milestone,
            $releaseChangelogEvent->repositoryName,
            $releaseChangelogEvent->version
        );
    }
}
