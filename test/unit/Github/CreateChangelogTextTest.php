<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Doctrine\AutomaticReleases\Github\CreateChangelogText;
use PHPUnit\Framework\TestCase;

final class CreateChangelogTextTest extends TestCase
{
    public function testGeneratedReleaseText() : void
    {
        self::assertSame(
            <<<'RELEASE'
Release [The title](http://example.com/milestone)

The description

Closed issues:

 *  [Issue](http://example.com/issue) thanks to [Magoo](http://example.com/author)
 * \[A label\] [PR](http://example.com/issue) thanks to [Magoo](http://example.com/author)

RELEASE
            ,
            (new CreateChangelogText())
                ->__invoke(Milestone::make([
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
                                    'nodes' => [
                                        [
                                            'color' => 'aabbcc',
                                            'name'  => 'A label',
                                            'url'   => 'http://example.com/a-label',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'url'          => 'http://example.com/milestone',
                ]))
        );
    }
}
