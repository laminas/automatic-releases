<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\IssueOrPullRequest;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Test\Unit\TestCase;

final class MilestoneTest extends TestCase
{
    public function test(): void
    {
        $milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => '1.2.3',
            'description'  => 'The description',
            'issues'       => [
                'nodes' => [
                    [
                        'number' => 456,
                        'title'  => 'Issue',
                        'author' => [
                            'login' => 'Magoo',
                            'url'   => 'https://example.com/author',
                        ],
                        'url'    => 'https://example.com/issue',
                        'closed' => true,
                        'labels' => [
                            'nodes' => [],
                        ],
                    ],
                ],
            ],
            'pullRequests' => [
                'nodes' => [
                    [
                        'number' => 789,
                        'title'  => 'PR',
                        'author' => [
                            'login' => 'Magoo',
                            'url'   => 'https://example.com/author',
                        ],
                        'url'    => 'https://example.com/issue',
                        'merged' => true,
                        'closed' => false,
                        'labels' => [
                            'nodes' => [],
                        ],
                    ],
                ],
            ],
            'url'          => 'https://github.com/vendor/project/releases/milestone/123',
        ]);

        self::assertEquals(
            [
                IssueOrPullRequest::fromPayload([
                    'number' => 456,
                    'title'  => 'Issue',
                    'author' => [
                        'login' => 'Magoo',
                        'url'   => 'https://example.com/author',
                    ],
                    'url'    => 'https://example.com/issue',
                    'closed' => true,
                    'labels' => [
                        'nodes' => [],
                    ],
                ]),
                IssueOrPullRequest::fromPayload([
                    'number' => 789,
                    'title'  => 'PR',
                    'author' => [
                        'login' => 'Magoo',
                        'url'   => 'https://example.com/author',
                    ],
                    'url'    => 'https://example.com/issue',
                    'merged' => true,
                    'closed' => false,
                    'labels' => [
                        'nodes' => [],
                    ],
                ]),
            ],
            $milestone->entries()
        );

        self::assertSame(123, $milestone->number());
        self::assertTrue($milestone->closed());
        self::assertSame('https://github.com/vendor/project/releases/milestone/123', $milestone->url()->__toString());
        self::assertSame('1.2.3', $milestone->title());
        self::assertSame('The description', $milestone->description());

        /** @psalm-suppress UnusedMethodCall */
        $milestone->assertAllIssuesAreClosed();
    }
}
