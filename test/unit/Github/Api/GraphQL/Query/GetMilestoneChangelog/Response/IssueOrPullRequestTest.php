<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Author;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\IssueOrPullRequest;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Label;
use PHPUnit\Framework\TestCase;

final class IssueOrPullRequestTest extends TestCase
{
    public function testIssue(): void
    {
        $issue = IssueOrPullRequest::fromPayload([
            'number' => 123,
            'title'  => 'Yadda',
            'author' => [
                'login' => 'Magoo',
                'url'   => 'https://example.com/author',
            ],
            'url'    => 'https://example.com/issue',
            'closed' => true,
            'labels' => [
                'nodes' => [
                    [
                        'name'  => 'BC Break',
                        'color' => 'abcabc',
                        'url'   => 'https://example.com/bc-break',
                    ],
                    [
                        'name'  => 'Question',
                        'color' => 'defdef',
                        'url'   => 'https://example.com/question',
                    ],
                ],
            ],
        ]);

        self::assertSame(123, $issue->number());
        self::assertTrue($issue->closed());
        self::assertSame('https://example.com/issue', $issue->url()->__toString());
        self::assertSame('Yadda', $issue->title());
        self::assertEquals(
            Author::fromPayload([
                'login' => 'Magoo',
                'url'   => 'https://example.com/author',
            ]),
            $issue->author()
        );
        self::assertEquals(
            [
                Label::fromPayload([
                    'name'  => 'BC Break',
                    'color' => 'abcabc',
                    'url'   => 'https://example.com/bc-break',
                ]),
                Label::fromPayload([
                    'name'  => 'Question',
                    'color' => 'defdef',
                    'url'   => 'https://example.com/question',
                ]),
            ],
            $issue->labels()
        );
    }

    public function testPullRequest(): void
    {
        $issue = IssueOrPullRequest::fromPayload([
            'number' => 123,
            'title'  => 'Yadda',
            'author' => [
                'login' => 'Magoo',
                'url'   => 'http://example.com/author',
            ],
            'url'    => 'http://example.com/issue',
            'closed' => false,
            'merged' => true,
            'labels' => [
                'nodes' => [
                    [
                        'name'  => 'BC Break',
                        'color' => 'abcabc',
                        'url'   => 'http://example.com/bc-break',
                    ],
                    [
                        'name'  => 'Question',
                        'color' => 'defdef',
                        'url'   => 'http://example.com/question',
                    ],
                ],
            ],
        ]);

        self::assertSame(123, $issue->number());
        self::assertTrue($issue->closed());
        self::assertSame('http://example.com/issue', $issue->url()->__toString());
        self::assertSame('Yadda', $issue->title());
        self::assertEquals(
            Author::fromPayload([
                'login' => 'Magoo',
                'url'   => 'http://example.com/author',
            ]),
            $issue->author()
        );
        self::assertEquals(
            [
                Label::fromPayload([
                    'name'  => 'BC Break',
                    'color' => 'abcabc',
                    'url'   => 'http://example.com/bc-break',
                ]),
                Label::fromPayload([
                    'name'  => 'Question',
                    'color' => 'defdef',
                    'url'   => 'http://example.com/question',
                ]),
            ],
            $issue->labels()
        );
    }
}
