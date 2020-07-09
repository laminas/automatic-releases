<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneFirst100IssuesAndPullRequests;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunQuery;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @covers \Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneFirst100IssuesAndPullRequests */
final class GetMilestoneFirst100IssuesAndPullRequestsTest extends TestCase
{
    /** @var RunQuery&MockObject */
    private $runQuery;

    /** @var GetMilestoneFirst100IssuesAndPullRequests */
    private $query;

    protected function setUp() : void
    {
        parent::setUp();

        $this->runQuery = $this->createMock(RunQuery::class);
        $this->query    = new GetMilestoneFirst100IssuesAndPullRequests($this->runQuery);
    }

    public function testRetrievesMilestone() : void
    {
        $this->runQuery
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::anything(),
                [
                    'repositoryName'  => 'bar',
                    'owner'           => 'foo',
                    'milestoneNumber' => 123,
                ]
            )
            ->willReturn([
                'repository' => [
                    'milestone' => [
                        'number'       => 123,
                        'closed'       => true,
                        'title'        => 'The title',
                        'description'  => 'The description',
                        'issues'       => [
                            'nodes' => [
                                [
                                    'number' => 456,
                                    'title'  => 'Issue',
                                    'author' => [
                                        'login' => 'Magoo',
                                        'url'   => 'http://example.com/author',
                                    ],
                                    'url'    => 'http://example.com/issue',
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
                                        'url'   => 'http://example.com/author',
                                    ],
                                    'url'    => 'http://example.com/issue',
                                    'merged' => true,
                                    'closed' => false,
                                    'labels' => [
                                        'nodes' => [],
                                    ],
                                ],
                            ],
                        ],
                        'url'          => 'http://example.com/milestone',
                    ],
                ],
            ]);

        $this->query->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            123
        );
    }
}
