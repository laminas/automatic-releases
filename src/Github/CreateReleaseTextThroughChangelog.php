<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;

use function array_keys;
use function str_replace;

final class CreateReleaseTextThroughChangelog implements CreateReleaseText
{
    private const TEMPLATE = <<<'MARKDOWN'
Release %release%

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
    ): string {
        $replacements = [
            '%release%'      => $this->markdownLink($milestone->title(), $milestone->url()),
            '%description%'  => (string) $milestone->description(),
            '%changelogText%' => $this->generateChangelog->__invoke(
                $repositoryName,
                $semVerVersion
            ),
        ];

        $text = str_replace(
            array_keys($replacements),
            $replacements,
            self::TEMPLATE
        );

        Assert::stringNotEmpty($text);

        return $text;
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
}
