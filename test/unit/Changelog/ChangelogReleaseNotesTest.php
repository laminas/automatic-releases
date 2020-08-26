<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_slice;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;

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
        $filename = tempnam(sys_get_temp_dir(), 'ChangelogReleaseNotes');
        Assert::stringNotEmpty($filename);
        file_put_contents($filename, 'Original contents');

        $entry           = new ChangelogEntry();
        $entry->contents = 'Changelog contents';
        $releaseNotes    = new ChangelogReleaseNotes('Changelog contents', $entry);

        $releaseNotes::writeChangelogFile($filename, $releaseNotes);

        $this->assertStringEqualsFile($filename, 'Original contents');
    }

    public function testWriteChangelogRewritesFileWithNewContents(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'ChangelogReleaseNotes');
        Assert::stringNotEmpty($filename);
        file_put_contents($filename, self::CHANGELOG_STUB);

        $requiredString = implode(
            "\n",
            array_slice(
                explode("\n", self::CHANGELOG_STUB),
                0,
                4
            )
        );

        $contents = sprintf(self::CHANGELOG_ENTRY, '2020-01-01');
        Assert::stringNotEmpty($contents);

        $entry           = new ChangelogEntry();
        $entry->contents = sprintf(self::CHANGELOG_ENTRY, 'TBD');
        $entry->index    = 4;
        $entry->length   = 22;

        $releaseNotes = new ChangelogReleaseNotes($contents, $entry);

        $releaseNotes::writeChangelogFile($filename, $releaseNotes);

        $contents = file_get_contents($filename);

        $this->assertStringContainsString($requiredString, $contents);
        $this->assertStringContainsString($releaseNotes->contents(), $contents);
    }

    public function testMergeRaisesExceptionIfBothCurrentAndNextInstanceContainChangelogEntries(): void
    {
        $entry           = new ChangelogEntry();
        $entry->contents = 'Some contents';

        $original = new ChangelogReleaseNotes('New contents', $entry);
        $toMerge  = new ChangelogReleaseNotes('Other contents', $entry);

        $this->expectException(RuntimeException::class);
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
