<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psl\Regex;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Psr\Http\Message\UriInterface;

use function preg_quote;

final readonly class CreateReleaseTextThroughChangelog implements CreateReleaseText
{
    private const TEMPLATE = <<<'MARKDOWN'
### Release Notes for %release%

%description%

%changelogText%
MARKDOWN;

    private const HEADING_REGEX = '/^#+\s+(.+)$/m';

final readonly class CreateReleaseTextThroughChangelog implements CreateReleaseText
{
    private const TEMPLATE = <<<'MARKDOWN'
        ### Release Notes for %release%
        
        %description%
        
        %changelogText%
        
        MARKDOWN;

    public function __construct(private readonly GenerateChangelog $generateChangelog)
    {
    }

    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory,
    ): ChangelogReleaseNotes {
        $text = Str\replace_every(self::TEMPLATE, [
            '%release%'      => $this->markdownLink($milestone->title(), $milestone->url()),
            '%description%'  => (string) $milestone->description(),
            '%changelogText%' => $this->normalizeChangelog(
                $this->generateChangelog->__invoke(
                    $repositoryName,
                    $semVerVersion,
                ),
                $semVerVersion->fullReleaseName(),
            ),
        ]);

        Type\non_empty_string()->assert($text);

        return new ChangelogReleaseNotes($this->formatChangelogText($text));
        return new ChangelogReleaseNotes($text);
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory,
    ): bool {
        return true;
    }

    private function markdownLink(string $text, UriInterface $uri): string
    {
        return '[' . $text . '](' . $uri->__toString() . ')';
    }

    private function normalizeChangelog(string $changelog, string $version): string
    {
        $changelog = $this->normalizeChangelogHeadings($changelog);
        $changelog = $this->removeRedundantVersionHeadings($changelog, $version);

        return $this->collapseMultiLineBreaks($changelog);
    }

    private function formatChangelogText(string $text): string
    {
        $lines = Str\split($text, "\n");
        $formattedLines = [];
        $inList = false;

        foreach ($lines as $line) {
            if (Regex\matches($line, self::HEADING_REGEX)) {
                if ($inList) {
                    $formattedLines[] = '';
                    $inList = false;
                }
                $formattedLines[] = $line;
                $formattedLines[] = '';
            } elseif (Str\starts_with(Str\trim($line), '-')) {
                $formattedLines[] = $line;
                $inList = true;
            } elseif ($inList && Str\trim($line) !== '') {
                $formattedLines[count($formattedLines) - 1] .= ' ' . Str\trim($line);
            } elseif (Str\trim($line) !== '') {
                $formattedLines[] = $line;
                $inList = false;
            }
        }

        return Str\join($formattedLines, "\n");

    }

    /**
     * Normalize changelog headings
     *
     * Markdown has two separate headings styles. One uses varying numbers of
     * `#` prefixes, another puts 3 or more of specific delimeters on the
     * following line (`===` for H1, `---` for H2).
     *
     * The CHANGELOG.md file, when using Keep A Changelog format, uses `#`
     * prefixes, while jwage/changelog-generator uses delimiter lines. This
     * method normalizes the latter to conform with the former, though pushing
     * the header two levels deeper so it can be embedded in a specific
     * changelog revision.
     */
    private function normalizeChangelogHeadings(string $changelog): string
    {
        $lines         = Str\split(Str\trim($changelog), "\n");
        $linesToRemove = [];
        foreach ($lines as $i => $line) {
            // Does the line match one of the delimiter line types? If so,
            // capture that in $matches.
            $matches = Regex\first_match($line, '/^(?P<delimiter>-{3,}|={3,})$/', Regex\capture_groups(['delimiter']));
            if ($matches === null) {
                continue;
            }

            // Is this the first line? Then the delimiter is not for a header.
            if ($i === 0) {
                continue;
            }

            // Is the previous line empty, or does it have content? If no
            // content, then it's not a header delimiter.
            $previousLine = $lines[$i - 1];
            if (empty($previousLine)) {
                continue;
            }

            // Rewrite the header line to use the appropriate "#" prefix.
            // We will then remove the current line, as the delimiter is no
            // longer necessary.
            /** @psalm-var "-"|"=" $delimiter */
            $delimiter = $matches['delimiter'][0];
            /** @psalm-var non-empty-string $heading */
            $heading         = Str\replace_every($delimiter, ['-' => '####', '=' => '###']);
            $lines[$i - 1]   = $heading . ' ' . $previousLine;
            $linesToRemove[] = $i;
        }

        foreach ($linesToRemove as $index) {
            unset($lines[$index]);
        }

        return Str\join(Vec\values($lines), "\n");
    }

    private function collapseMultiLineBreaks(string $text): string
    {
        return Regex\replace($text, "/\n\n\n+/s", "\n\n");
    }

    private function removeRedundantVersionHeadings(string $changelog, string $version): string
    {
        return Regex\replace($changelog, "/\n\#{3,} " . preg_quote($version, '/') . "\n/s", '');
    }
}
