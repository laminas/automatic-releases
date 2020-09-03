<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;

use function array_keys;
use function explode;
use function implode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strtr;
use function trim;

final class CreateReleaseTextThroughChangelog implements CreateReleaseText
{
    private const TEMPLATE = <<<'MARKDOWN'
### Release Notes for %release%

%description%

%changelogText%

MARKDOWN;

    private GenerateChangelog $generateChangelog;

    public function __construct(GenerateChangelog $generateChangelog)
    {
        $this->generateChangelog = $generateChangelog;
    }

    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): ChangelogReleaseNotes {
        $replacements = [
            '%release%'      => $this->markdownLink($milestone->title(), $milestone->url()),
            '%description%'  => (string) $milestone->description(),
            '%changelogText%' => $this->normalizeChangelog(
                $this->generateChangelog->__invoke(
                    $repositoryName,
                    $semVerVersion
                ),
                $semVerVersion->fullReleaseName()
            ),
        ];

        $text = str_replace(
            array_keys($replacements),
            $replacements,
            self::TEMPLATE
        );

        Assert::stringNotEmpty($text);

        return new ChangelogReleaseNotes($text);
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
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
        $changelog = $this->collapseMultiLineBreaks($changelog);

        return $changelog;
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
        $lines         = explode("\n", trim($changelog));
        $linesToRemove = [];
        foreach ($lines as $i => $line) {
            $matches = [];

            // Does the line match one of the delimiter line types? If so,
            // capture that in $matches.
            if (! preg_match('/^(?P<delim>-{3,}|={3,})$/', $line, $matches)) {
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
            $delimiter = $matches['delim']{0};
            /** @psalm-var non-empty-string $heading */
            $heading         = strtr($delimiter, ['-' => '####', '=' => '###']);
            $lines[$i - 1]   = $heading . ' ' . $previousLine;
            $linesToRemove[] = $i;
        }

        foreach ($linesToRemove as $index) {
            unset($lines[$index]);
        }

        return implode("\n", $lines);
    }

    private function collapseMultiLineBreaks(string $text): string
    {
        return preg_replace("/\n\n\n+/s", "\n\n", $text);
    }

    private function removeRedundantVersionHeadings(string $changelog, string $version): string
    {
        return preg_replace("/\n\#{3,} " . preg_quote($version, '/') . "\n/s", '', $changelog);
    }
}
