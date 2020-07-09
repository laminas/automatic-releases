<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Doctrine\AutomaticReleases\Github\CreateReleaseTextThroughChangelog;
use Doctrine\AutomaticReleases\Github\GenerateChangelog;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;

final class CreateChangelogTextTest extends TestCase
{
    public function testGeneratedReleaseText(): void
    {
        $generateChangelog = $this->createMock(GenerateChangelog::class);

        $repositoryName = RepositoryName::fromFullName('doctrine/repository-name');
        $semVerVersion  = SemVerVersion::fromMilestoneName('1.0.0');

        $generateChangelog->expects(self::once())
            ->method('__invoke')
            ->with($repositoryName, $semVerVersion)
            ->willReturn('Generated changelog');

        self::assertSame(
            <<<'RELEASE'
Release [The title](http://example.com/milestone)

The description

Generated changelog

RELEASE
            ,
            (new CreateReleaseTextThroughChangelog($generateChangelog))
                ->__invoke(
                    Milestone::fromPayload([
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
                    ]),
                    $repositoryName,
                    $semVerVersion
                )
        );
    }
}
