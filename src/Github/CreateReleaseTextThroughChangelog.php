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
use function count;
use function explode;
use function implode;
use function preg_match;
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
            '%changelogText%' => $this->normalizeChangelogHeadings($this->generateChangelog->__invoke(
                $repositoryName,
                $semVerVersion
            )),
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

    private function normalizeChangelogHeadings(string $changelog): string
    {
        $lines         = explode("\n", trim($changelog));
        $lineCount     = count($lines);
        $linesToRemove = [];
        for ($i = 0; $i < $lineCount; $i += 1) {
            $line    = $lines[$i];
            $matches = [];
            if (! preg_match('/^(?P<delim>-{3,}|={3,})$/', $line, $matches)) {
                continue;
            }

            $previousLine = $lines[$i - 1];
            if (empty($previousLine)) {
                continue;
            }

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
}
