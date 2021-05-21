<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use PHPUnit\Framework\TestCase;
use Psl\Dict;
use Psl\Env;
use Psl\Filesystem;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use ReflectionProperty;
use RuntimeException;

/**
 * @covers \Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes
 */
class ChangelogReleaseNotesTest extends TestCase
{
    public function testInitialContentsAreThoseProvidedToConstructor(): void
    {
        $releaseNotes = new ChangelogReleaseNotes('contents');
        $this->assertSame('contents', $releaseNotes->contents());
    }

    public function testDoesNotRequireUpdatingChangelogFileIfNoEntryIsPresent(): void
    {
        $releaseNotes = new ChangelogReleaseNotes('contents');
        $this->assertFalse($releaseNotes->requiresUpdatingChangelogFile());
    }

    public function testDoesNotRequireUpdatingChangelogFileIfChangelogEntryContentsMatchReleaseNotes(): void
    {
        $entry           = new ChangelogEntry();
        $entry->contents = 'contents';
        $releaseNotes    = new ChangelogReleaseNotes('contents', $entry);
        $this->assertFalse($releaseNotes->requiresUpdatingChangelogFile());
    }

    public function testRequiresUpdatingChangelogFileIfChangelogEntryContentsDoNotMatchReleaseNotes(): void
    {
        $entry           = new ChangelogEntry();
        $entry->contents = 'contents';
        $releaseNotes    = new ChangelogReleaseNotes('new contents', $entry);
        $this->assertTrue($releaseNotes->requiresUpdatingChangelogFile());
    }

    public function testWriteChangelogIsNoOpIfNoUpdatesAreRequired(): void
    {
        $filename = Type\non_empty_string()->assert(
            Filesystem\create_temporary_file(Env\temp_dir(), 'ChangelogReleaseNotes')
        );

        Filesystem\write_file($filename, 'Original contents');

        $entry           = new ChangelogEntry();
        $entry->contents = 'Changelog contents';
        $releaseNotes    = new ChangelogReleaseNotes('Changelog contents', $entry);

        $releaseNotes::writeChangelogFile($filename, $releaseNotes);

        $this->assertStringEqualsFile($filename, 'Original contents');
    }

    public function testWriteChangelogRewritesFileWithNewContents(): void
    {
        $filename = Type\non_empty_string()
            ->assert(Filesystem\create_temporary_file(Env\temp_dir(), 'ChangelogReleaseNotes'));

        Filesystem\write_file($filename, self::CHANGELOG_STUB);

        $requiredString = Str\join(
            Vec\values(Dict\take(Str\split(self::CHANGELOG_STUB, "\n"), 4)),
            "\n",
        );

        $contents = Type\non_empty_string()->assert(Str\format(self::CHANGELOG_ENTRY, '2020-01-01'));

        $entry           = new ChangelogEntry();
        $entry->contents = Str\format(self::CHANGELOG_ENTRY, 'TBD');
        $entry->index    = 4;
        $entry->length   = 22;

        $releaseNotes = new ChangelogReleaseNotes($contents, $entry);

        $releaseNotes::writeChangelogFile($filename, $releaseNotes);

        $contents = Filesystem\read_file($filename);

        $this->assertStringContainsString($requiredString, $contents);
        $this->assertStringContainsString($releaseNotes->contents() . "\n", $contents);
    }

    public function testMergeRaisesExceptionIfBothCurrentAndNextInstanceContainChangelogEntries(): void
    {
        $entry           = new ChangelogEntry();
        $entry->contents = 'Some contents';

        $original = new ChangelogReleaseNotes('New contents', $entry);
        $toMerge  = new ChangelogReleaseNotes('Other contents', $entry);

        $this->expectException(RuntimeException::class);

        /** @psalm-suppress UnusedMethodCall */
        $original->merge($toMerge);
    }

    public function testMergeAppendsContentsOfNextInstanceWithCurrentAndReturnsNewInstance(): void
    {
        $original = new ChangelogReleaseNotes('original contents');
        $second   = new ChangelogReleaseNotes('secondary contents');
        $merged   = $original->merge($second);

        $this->assertNotSame($original, $merged);
        $this->assertNotSame($second, $merged);
        $this->assertMatchesRegularExpression(
            '/' . $original->contents() . '.*' . $second->contents() . '/s',
            $merged->contents()
        );
    }

    /**
     * @psalm-return iterable<
     *     string,
     *     array{
     *         0: ChangelogReleaseNotes,
     *         1: ChangelogReleaseNotes,
     *         2: ChangelogEntry,
     *     }
     * >
     */
    public function releaseNotesProvider(): iterable
    {
        $changelogEntry = new ChangelogEntry();

        yield 'original contains entry' => [
            new ChangelogReleaseNotes('original', $changelogEntry),
            new ChangelogReleaseNotes('secondary'),
            $changelogEntry,
        ];

        yield 'secondary contains entry' => [
            new ChangelogReleaseNotes('original'),
            new ChangelogReleaseNotes('secondary', $changelogEntry),
            $changelogEntry,
        ];
    }

    /**
     * @dataProvider releaseNotesProvider
     */
    public function testMergedInstanceContainsChangelogEntryFromTheInstanceThatHadOne(
        ChangelogReleaseNotes $original,
        ChangelogReleaseNotes $secondary,
        ChangelogEntry $expectedEntry
    ): void {
        $merged = $original->merge($secondary);

        $r = new ReflectionProperty($merged, 'changelogEntry');
        $r->setAccessible(true);

        // Equals, but not same, as the class stores a clone of the original.
        $this->assertEquals($expectedEntry, $r->getValue($merged));
    }

    private const CHANGELOG_ENTRY = <<< 'ENTRY'
        ## 1.0.1 - %s
        
        ### Added
        
        - Nothing.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Fixed a bug.

        ENTRY;

    private const CHANGELOG_STUB = <<< 'CHANGELOG'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
        
        ## 1.0.1 - TBD
        
        ### Added
        
        - Nothing.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Fixed a bug.

        CHANGELOG;
}
