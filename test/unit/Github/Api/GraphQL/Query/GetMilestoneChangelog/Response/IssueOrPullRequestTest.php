<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Author;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\IssueOrPullRequest;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Label;
use PHPUnit\Framework\TestCase;

final class IssueOrPullRequestTest extends TestCase
{
    public function testIssue() : void
    {
        $issue = IssueOrPullRequest::make([
            'number' => 123,
            'title'  => 'Yadda',
            'author' => [
                'login' => 'Magoo',
                'url'   => 'http://example.com/author',
            ],
            'url'    => 'http://example.com/issue',
            'closed' => true,
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
            Author::make([
                'login' => 'Magoo',
                'url'   => 'http://example.com/author',
            ]),
            $issue->author()
        );
        self::assertEquals(
            [
                Label::make([
                    'name'  => 'BC Break',
                    'color' => 'abcabc',
                    'url'   => 'http://example.com/bc-break',
                ]),
                Label::make([
                    'name'  => 'Question',
                    'color' => 'defdef',
                    'url'   => 'http://example.com/question',
                ]),
            ],
            $issue->labels()
        );
    }

    public function testPullRequest() : void
    {
        $issue = IssueOrPullRequest::make([
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
            Author::make([
                'login' => 'Magoo',
                'url'   => 'http://example.com/author',
            ]),
            $issue->author()
        );
        self::assertEquals(
            [
                Label::make([
                    'name'  => 'BC Break',
                    'color' => 'abcabc',
                    'url'   => 'http://example.com/bc-break',
                ]),
                Label::make([
                    'name'  => 'Question',
                    'color' => 'defdef',
                    'url'   => 'http://example.com/question',
                ]),
            ],
            $issue->labels()
        );
    }
}
