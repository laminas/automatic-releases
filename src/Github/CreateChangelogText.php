<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\IssueOrPullRequest;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Label;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Psr\Http\Message\UriInterface;
use function array_keys;
use function array_map;
use function implode;
use function str_replace;

final class CreateChangelogText
{
    private const TEMPLATE = <<<'MARKDOWN'
Release %release%

%description%

Closed issues:

 * %closedIssues%

MARKDOWN;

    public function __invoke(Milestone $milestone) : string
    {
        $replacements = [
            '%release%'      => $this->markdownLink($milestone->title(), $milestone->url()),
            '%description%'  => $milestone->description(),
            '%closedIssues%' => implode("\n * ", array_map([$this, 'entryToRow'], $milestone->entries())),
        ];

        return str_replace(
            array_keys($replacements),
            $replacements,
            self::TEMPLATE
        );
    }

    private function entryToRow(IssueOrPullRequest $issueOrPullRequest) : string
    {
        $author = $issueOrPullRequest->author();

        return implode(' ', array_map([$this, 'labelToString'], $issueOrPullRequest->labels()))
            . ' '
            . $this->markdownLink($issueOrPullRequest->title(), $issueOrPullRequest->url())
            . ' thanks to '
            . $this->markdownLink($author->name(), $author->url());
    }

    private function labelToString(Label $label) : string
    {
        return '\\[' . $label->name() . '\\]';
    }

    private function markdownLink(string $text, UriInterface $uri) : string
    {
        return '[' . $text . '](' . $uri->__toString() . ')';
    }
}
