<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\CreateReleaseTextThroughChangelog;
use Laminas\AutomaticReleases\Github\GenerateChangelog;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;

final class CreateChangelogTextTest extends TestCase
{
    public function testGeneratedReleaseText(): void
    {
        $generatedReleaseNotes = <<< 'NOTES'
            -----


            2.12.3
            ======

            - Total issues resolved: 0
            - Total pull requests resolved: 1
            - Total contributors: 1


            -----


            Bug
            ---


            - [999: Some bug that got fixed](https://www.example.com/issues/999) thanks to @somebody

            NOTES;

        $generateChangelog = $this->createMock(GenerateChangelog::class);

        $repositoryName = RepositoryName::fromFullName('laminas/repository-name');
        $semVerVersion  = SemVerVersion::fromMilestoneName('2.12.3');

        $generateChangelog->expects(self::once())
            ->method('__invoke')
            ->with($repositoryName, $semVerVersion)
            ->willReturn($generatedReleaseNotes);

        self::assertSame(
            <<< 'RELEASE'
                ### Release Notes for [The title](http://example.com/milestone)
                
                The description
                
                -----
                
                - Total issues resolved: 0
                - Total pull requests resolved: 1
                - Total contributors: 1
                
                -----
                
                #### Bug
                
                - [999: Some bug that got fixed](https://www.example.com/issues/999) thanks to @somebody
                
                RELEASE,
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
                    $semVerVersion,
                    BranchName::fromName('2.12.x'),
                    __DIR__
                )
                ->contents()
        );
    }

    public function testCapableOfGeneratingReleaseTest(): void
    {
        $generateChangelog = $this->createMock(GenerateChangelog::class);

        $repositoryName = RepositoryName::fromFullName('laminas/repository-name');
        $semVerVersion  = SemVerVersion::fromMilestoneName('1.0.0');

        self::assertTrue(
            (new CreateReleaseTextThroughChangelog($generateChangelog))
                ->canCreateReleaseText(
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
                    $semVerVersion,
                    BranchName::fromName('1.0.x'),
                    __DIR__
                )
        );
    }
}
