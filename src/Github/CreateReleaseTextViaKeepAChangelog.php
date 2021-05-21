<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Changelog\ChangelogExists;
use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Lcobucci\Clock\Clock;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use Phly\KeepAChangelog\Common\ChangelogParser;
use Phly\KeepAChangelog\Exception\ExceptionInterface;
use Psl;
use Psl\Iter;
use Psl\Regex;
use Psl\Shell;
use Psl\Str;
use Psl\Type;

use function preg_quote;

class CreateReleaseTextViaKeepAChangelog implements CreateReleaseText
{
    /** @psalm-var list<string> */
    private const DEFAULT_SECTIONS = [
        'Added',
        'Changed',
        'Deprecated',
        'Removed',
        'Fixed',
    ];

    private ChangelogExists $changelogExists;
    private Clock $clock;

    public function __construct(ChangelogExists $changelogExists, Clock $clock)
    {
        $this->changelogExists = $changelogExists;
        $this->clock           = $clock;
    }

    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): ChangelogReleaseNotes {
        $changelogEntry = $this->fetchChangelogEntry(
            $this->fetchChangelogContentsFromBranch($sourceBranch, $repositoryDirectory),
            $semVerVersion->fullReleaseName()
        );

        $contents = $changelogEntry->contents();

        Psl\invariant(! Str\is_empty($contents), 'Detected changelog entry for version, but retrieval failed');

        return new ChangelogReleaseNotes(
            $this->updateReleaseDate(
                $this->removeDefaultContents($contents),
                $semVerVersion->fullReleaseName()
            ),
            $changelogEntry
        );
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            return false;
        }

        try {
            $changelog = (new ChangelogParser())
                ->findChangelogForVersion(
                    $this->fetchChangelogContentsFromBranch($sourceBranch, $repositoryDirectory),
                    $semVerVersion->fullReleaseName()
                );

            return ! Str\is_empty($changelog);
        } catch (ExceptionInterface) {
            return false;
        }
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     *
     * @psalm-return non-empty-string
     */
    private function fetchChangelogContentsFromBranch(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        $contents = Shell\execute('git', ['show', 'origin/' . $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);

        return Type\non_empty_string()->assert($contents);
    }

    /**
     * @psalm-param non-empty-string $changelog
     * @psalm-param non-empty-string $version
     *
     * @psalm-return non-empty-string
     */
    private function updateReleaseDate(string $changelog, string $version): string
    {
        $lines = Str\Byte\split($changelog, "\n");

        Psl\invariant(Iter\count($lines) >= 0, 'Empty changelog detected.');

        $releaseLine = $lines[0];
        $regex       = Type\non_empty_string()
            ->assert(Str\format('/^(## (?:%1$s|\[%1$s\])).*$/i', preg_quote($version)));
        $lines[0]    = Regex\replace($releaseLine, $regex, '$1 - ' . $this->clock->now()->format('Y-m-d'));

        return Type\non_empty_string()->assert(Str\join($lines, "\n"));
    }

    /**
     * @psalm-param non-empty-string $changelog
     *
     * @psalm-return non-empty-string
     */
    private function removeDefaultContents(string $changelog): string
    {
        $contents = Iter\reduce(
            self::DEFAULT_SECTIONS,
            static fn (string $changelog, string $section): string => Regex\replace(
                $changelog,
                "/\n\#{3} " . $section . "\n\n- Nothing.\n/s",
                '',
            ),
            $changelog
        );

        return Type\non_empty_string()->assert($contents);
    }

    /**
     * Fetch a changelog entry for a given version from the changelog contents
     *
     * Each entry starts with one of the following:
     *
     * - Most basic: the version identifier
     * <code class="markdown">
     * ## {VERSION}
     * </code>
     *
     * - A linked version identifier (where the link appears at the end of the
     *   document)
     * <code class="markdown">
     * ## [{VERSION}]
     * </code>
     *
     * - Arbitrary linked version name; generally used to delimit links at the
     *   end of the file:
     * <code class="markdown">
     * ## [{VERSION_NAME}]:
     * </code>
     *
     * This code goes line by line through the contents, looking for a line
     * matching one of the first two cases; if found, that indicates the start
     * of where that changelog entry exists in the file, and we store the index
     * of that line.
     *
     * We then iterate until we find the next release boundary (any of the above
     * three types), keeping a count of lines. When a boundary is found, we
     * create a ChangelogEntry using:
     *
     * - The concatenated contents discovered for that version.
     * - The index of where the version starts in the contents.
     * - The number of lines discovered for that version.
     *
     * This information can be used later to overwrite the contents for that
     * version.
     *
     * @psalm-param non-empty-string $contents
     * @psalm-param non-empty-string $version
     */
    private function fetchChangelogEntry(string $contents, string $version): ChangelogEntry
    {
        $entryContents = [];
        $entryIndex    = null;
        $entryLength   = 0;
        $boundaryRegex = '/^(?:## (?:\d+\.\d+\.\d+|\[\d+\.\d+\.\d+\])|\[.*?\]:\s*\S+)/i';
        $regex         = Type\non_empty_string()
            ->assert(Str\format('/^## (?:%1$s|\[%1$s\])/i', preg_quote($version)));

        foreach (Str\Byte\split($contents, "\n") as $index => $line) {
            // If we identified an entry for our version previously, and have
            // now reached a boundary, we are done.
            if ($entryIndex && Regex\matches($line, $boundaryRegex)) {
                break;
            }

            // Did we identify the starting line for the requested version?
            if (Regex\matches($line, $regex)) {
                $entryContents[] = $line;
                $entryIndex      = $index;
                $entryLength     = 1;
                continue;
            }

            // Are we currently in the contents for the requested version? If
            // not, move on to the next line.
            if (! $entryIndex) {
                continue;
            }

            // Update the contents for this version, and increase the line
            // count discovered.
            $entryContents[] = $line;
            $entryLength    += 1;
        }

        Psl\invariant($entryIndex !== null, 'Could not find entry for version ' . $version . ' in project CHANGELOG.md file');

        $entryContents = Str\join($entryContents, "\n");

        $entry           = new ChangelogEntry();
        $entry->contents = $entryContents;
        $entry->index    = $entryIndex;
        $entry->length   = $entryLength;

        return $entry;
    }
}
