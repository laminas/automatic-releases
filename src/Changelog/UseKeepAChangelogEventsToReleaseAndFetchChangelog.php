<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Phly\KeepAChangelog\Version\ReadyLatestChangelogEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

use function assert;
use function date;
use function file_exists;
use function sprintf;

class UseKeepAChangelogEventsToReleaseAndFetchChangelog implements
    ReleaseChangelogAndFetchContents
{
    private CommitFile $commitFile;
    private EventDispatcherInterface $dispatcher;
    private Push $push;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        CommitFile $commitFile,
        Push $push
    ) {
        $this->dispatcher = $dispatcher;
        $this->commitFile = $commitFile;
        $this->push       = $push;
    }

    public function __invoke(ReleaseChangelogEvent $releaseChangelogEvent): ?string
    {
        if (! file_exists($releaseChangelogEvent->repositoryDirectory . '/CHANGELOG.md')) {
            // No CHANGELOG.md present; cannot handle.
            return null;
        }

        $version = $releaseChangelogEvent->version->fullReleaseName();

        $event = $this->dispatcher->dispatch(new ReadyLatestChangelogEvent(
            $releaseChangelogEvent->input,
            $releaseChangelogEvent->output,
            $this->dispatcher,
            date('Y-M-d'),
            $version
        ));
        assert($event instanceof ReadyLatestChangelogEvent);

        if ($event->failed()) {
            return null;
        }

        // Commit file
        ($this->commitFile)(
            $releaseChangelogEvent->repositoryDirectory,
            'CHANGELOG.md',
            sprintf('%s readiness', $version),
            $releaseChangelogEvent->author
        );

        // Push changes
        ($this->push)(
            $releaseChangelogEvent->repositoryDirectory,
            $releaseChangelogEvent->sourceBranch->name()
        );

        // Pull and return changelog entry from event
        $changelogEntry = $event->changelogEntry();

        return $changelogEntry
            ? $changelogEntry->contents()
            : sprintf(
                '# %s/%s %s',
                $releaseChangelogEvent->repositoryName->owner(),
                $releaseChangelogEvent->repositoryName->name(),
                $releaseChangelogEvent->version->fullReleaseName()
            );
    }
}
