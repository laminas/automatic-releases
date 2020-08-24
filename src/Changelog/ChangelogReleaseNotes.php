<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Phly\KeepAChangelog\Common\ChangelogEditor;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use RuntimeException;
use Webmozart\Assert\Assert;

class ChangelogReleaseNotes
{
    private const CONCATENATION_STRING = "\n\n-----\n\n";

    private ?ChangelogEntry $changelogEntry;

    /** @psalm-var non-empty-string */
    private string $contents;

    /**
     * @psalm-param non-empty-string $contents
     */
    public function __construct(
        string $contents,
        ?ChangelogEntry $changelogEntry = null
    ) {
        $this->contents       = $contents;
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

        $merged                 = clone $this;
        $merged->contents      .= self::CONCATENATION_STRING . $next->contents;
        $merged->changelogEntry = $merged->changelogEntry ?: $next->changelogEntry;

        return $merged;
    }

    public function requiresUpdatingChangelogFile(): bool
    {
        if ($this->changelogEntry === null) {
            return false;
        }

        $originalContents = (string) $this->changelogEntry->contents();

        return $this->contents !== $originalContents;
    }

    /**
     * @psalm-param non-empty-string $changelogFile
     */
    public function writeChangelogFile(string $changelogFile): void
    {
        // Nothing to do
        if (! $this->requiresUpdatingChangelogFile()) {
            return;
        }

        Assert::notNull($this->changelogEntry);

        $editor = new ChangelogEditor();
        $editor->update(
            $changelogFile,
            $this->contents,
            $this->changelogEntry
        );
    }
}
