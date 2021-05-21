<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Phly\KeepAChangelog\Common\ChangelogEditor;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use Psl;
use Psl\Str;
use RuntimeException;

/**
 * @psalm-immutable
 */
class ChangelogReleaseNotes
{
    private const CONCATENATION_STRING = "\n\n-----\n\n";

    private ?ChangelogEntry $changelogEntry;

    /** @psalm-var non-empty-string */
    private string $contents;

    /**
     * @psalm-param non-empty-string $changelogFile
     */
    public static function writeChangelogFile(string $changelogFile, self $releaseNotes): void
    {
        // Nothing to do
        if (! $releaseNotes->requiresUpdatingChangelogFile()) {
            return;
        }

        Psl\invariant($releaseNotes->changelogEntry !== null, 'Release does not contain a changelog entry.');

        $changelogEntry = Str\trim_right($releaseNotes->contents, "\n") . "\n\n";

        $editor = new ChangelogEditor();
        $editor->update(
            $changelogFile,
            $changelogEntry,
            $releaseNotes->changelogEntry
        );
    }

    /**
     * @psalm-param non-empty-string $contents
     */
    public function __construct(
        string $contents,
        ?ChangelogEntry $changelogEntry = null
    ) {
        if ($changelogEntry) {
            $changelogEntry = clone $changelogEntry;
        }

        $this->contents = $contents;

        /** @psalm-suppress ImpurePropertyAssignment */
        $this->changelogEntry = $changelogEntry;
    }

    /**
     * @psalm-return non-empty-string
     */
    public function contents(): string
    {
        return $this->contents;
    }

    public function merge(self $next): self
    {
        if ($this->changelogEntry && $next->changelogEntry) {
            throw new RuntimeException(
                'Aborting: Both current release notes and next contain a ChangelogEntry;'
                . ' only one CreateReleaseText implementation should resolve one.'
            );
        }

        $changelogEntry = $this->changelogEntry ?: $next->changelogEntry;
        if ($changelogEntry) {
            $changelogEntry = clone $changelogEntry;
        }

        $merged            = clone $this;
        $merged->contents .= self::CONCATENATION_STRING . $next->contents;
        /** @psalm-suppress ImpurePropertyAssignment */
        $merged->changelogEntry = $changelogEntry;

        return $merged;
    }

    public function requiresUpdatingChangelogFile(): bool
    {
        if ($this->changelogEntry === null) {
            return false;
        }

        return $this->contents !== $this->changelogEntry->contents();
    }
}
