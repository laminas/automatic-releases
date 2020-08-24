<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use InvalidArgumentException;
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
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_replace;

class CreateReleaseTextViaKeepAChangelog implements CreateReleaseText
{
    private const DEFAULT_CONTENTS = <<< 'CONTENTS'
        ### Added
        
        - Nothing.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Nothing.
        
        CONTENTS;


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
        Assert::stringNotEmpty($contents, 'Detected changelog entry for version, but retrieval failed');

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

            Assert::notEmpty($changelog);

            return true;
        } catch (ExceptionInterface | InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     * @psalm-return non-empty-string
     */
    private function fetchChangelogContentsFromBranch(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        $process = new Process(['git', 'show', 'origin/' . $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);
        $process->mustRun();

        $contents = $process->getOutput();
        Assert::notEmpty($contents);

        return $contents;
    }

    /**
     * @psalm-param non-empty-string $changelog
     * @psalm-param non-empty-string $version
     * @psalm-return non-empty-string
     */
    private function updateReleaseDate(string $changelog, string $version): string
    {
        $lines = explode("\n", $changelog);
        Assert::greaterThan(count($lines), 0);

        $releaseLine = $lines[0];
        $regex       = sprintf('/^(## (?:%1$s|\[%1$s\])).*$/i', preg_quote($version));
        $lines[0]    = preg_replace($regex, '$1 - ' . $this->clock->now()->format('Y-m-d'), $releaseLine);

        return implode("\n", $lines);
    }

    /**
     * @psalm-param non-empty-string $changelog
     * @psalm-return non-empty-string
     */
    private function removeDefaultContents(string $changelog): string
    {
        $contents = str_replace(self::DEFAULT_CONTENTS, '', $changelog);
        Assert::notEmpty($contents);

        return $contents;
    }

    /**
     * @psalm-param non-empty-string $contents
     * @psalm-param non-empty-string $version
     */
    private function fetchChangelogEntry(string $contents, string $version): ChangelogEntry
    {
        $entryContents = [];
        $entryIndex    = null;
        $entryLength   = 0;
        $boundaryRegex = '/^(?:## (?:\d+\.\d+\.\d+|\[\d+\.\d+\.\d+\])|\[.*?\]:\s*\S+)/i';
        $regex         = sprintf('/^## (?:%1$s|\[%1$s\])/i', preg_quote($version));

        foreach (explode("\n", $contents) as $index => $line) {
            if ($entryIndex && preg_match($boundaryRegex, $line)) {
                break;
            }

            if (preg_match($regex, $line)) {
                $entryContents[] = $line;
                $entryIndex      = $index;
                $entryLength     = 1;
                continue;
            }

            if (! $entryIndex) {
                continue;
            }

            $entryContents[] = $line;
            $entryLength    += 1;
        }

        Assert::integer($entryIndex, 'Could not find entry for version ' . $version . ' in project CHANGELOG.md file');

        $entryContents = implode("\n", $entryContents);
        Assert::stringNotEmpty($entryContents);

        $entry           = new ChangelogEntry();
        $entry->contents = $entryContents;
        $entry->index    = $entryIndex;
        $entry->length   = $entryLength;

        return $entry;
    }
}
