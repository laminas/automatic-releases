<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\AppendingCreateReleaseTextAggregate;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function assert;
use function range;

final class AppendingCreateReleaseTextAggregateTest extends TestCase
{
    private Milestone $milestone;

    private RepositoryName $repositoryName;

    /** @psalm-var non-empty-string */
    private string $repositoryPath;

    private BranchName $sourceBranch;

    private SemVerVersion $version;

    protected function setUp(): void
    {
        $this->milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => 'The title',
            'description'  => 'The description',
            'issues'       => [
                'nodes' => [],
            ],
            'pullRequests' => [
                'nodes' => [],
            ],
            'url'          => 'http://example.com/milestone',
        ]);

        $this->repositoryName = RepositoryName::fromFullName('example/repo');
        $this->repositoryPath = __DIR__;
        $this->sourceBranch   = BranchName::fromName('1.0.x');
        $this->version        = SemVerVersion::fromMilestoneName('1.0.1');
    }

    public function testIndicatesCannotCreateReleaseTextIfNoStrategiesCan(): void
    {
        $strategies = [];
        foreach (range(0, 4) as $index) {
            $strategy = $this->createMock(CreateReleaseText::class);
            assert($strategy instanceof CreateReleaseText);
            assert($strategy instanceof MockObject);

            $strategy
                ->expects($this->once())
                ->method('canCreateReleaseText')
                ->with(
                    $this->equalTo($this->milestone),
                    $this->equalTo($this->repositoryName),
                    $this->equalTo($this->version),
                    $this->equalTo($this->sourceBranch),
                    $this->equalTo($this->repositoryPath)
                )
                ->willReturn(false);
            $strategies[] = $strategy;
        }

        $createReleaseText = new AppendingCreateReleaseTextAggregate($strategies);

        $this->assertFalse(
            $createReleaseText->canCreateReleaseText(
                $this->milestone,
                $this->repositoryName,
                $this->version,
                $this->sourceBranch,
                $this->repositoryPath
            )
        );
    }

    public function testIndicatesCanCreateReleaseTextIfAtLeastOneStrategyCan(): void
    {
        $strategies = [];
        foreach (range(0, 4) as $index) {
            $strategy = $this->createMock(CreateReleaseText::class);
            assert($strategy instanceof CreateReleaseText);
            assert($strategy instanceof MockObject);

            if ($index < 2) {
                $strategy
                    ->expects($this->once())
                    ->method('canCreateReleaseText')
                    ->with(
                        $this->equalTo($this->milestone),
                        $this->equalTo($this->repositoryName),
                        $this->equalTo($this->version),
                        $this->equalTo($this->sourceBranch),
                        $this->equalTo($this->repositoryPath)
                    )
                    ->willReturn(false);
                $strategies[] = $strategy;
                continue;
            }

            if ($index > 2) {
                $strategy
                    ->expects($this->never())
                    ->method('canCreateReleaseText');
                $strategies[] = $strategy;
                continue;
            }

            $strategy
                ->expects($this->once())
                ->method('canCreateReleaseText')
                ->with(
                    $this->equalTo($this->milestone),
                    $this->equalTo($this->repositoryName),
                    $this->equalTo($this->version),
                    $this->equalTo($this->sourceBranch),
                    $this->equalTo($this->repositoryPath)
                )
                ->willReturn(true);
            $strategies[] = $strategy;
        }

        $createReleaseText = new AppendingCreateReleaseTextAggregate($strategies);

        $this->assertTrue(
            $createReleaseText->canCreateReleaseText(
                $this->milestone,
                $this->repositoryName,
                $this->version,
                $this->sourceBranch,
                $this->repositoryPath
            )
        );
    }

    public function testReturnsConcatenatedValuesFromStrategiesThatCanCreateReleaseText(): void
    {
        $strategies = [];
        foreach (range(0, 4) as $index) {
            $strategy = $this->createMock(CreateReleaseText::class);
            assert($strategy instanceof CreateReleaseText);
            assert($strategy instanceof MockObject);

            switch ($index) {
                case 0:
                    // fall-through
                case 2:
                    // fall-through
                case 4:
                    $strategy
                        ->expects($this->once())
                        ->method('canCreateReleaseText')
                        ->with(
                            $this->equalTo($this->milestone),
                            $this->equalTo($this->repositoryName),
                            $this->equalTo($this->version),
                            $this->equalTo($this->sourceBranch),
                            $this->equalTo($this->repositoryPath)
                        )
                        ->willReturn(true);
                    $strategy
                        ->expects($this->once())
                        ->method('__invoke')
                        ->with(
                            $this->equalTo($this->milestone),
                            $this->equalTo($this->repositoryName),
                            $this->equalTo($this->version),
                            $this->equalTo($this->sourceBranch),
                            $this->equalTo($this->repositoryPath)
                        )
                        ->willReturn('STRATEGY ' . $index);
                    break;
                default:
                    $strategy
                        ->expects($this->once())
                        ->method('canCreateReleaseText')
                        ->with(
                            $this->equalTo($this->milestone),
                            $this->equalTo($this->repositoryName),
                            $this->equalTo($this->version),
                            $this->equalTo($this->sourceBranch),
                            $this->equalTo($this->repositoryPath)
                        )
                        ->willReturn(false);
                    $strategy
                        ->expects($this->never())
                        ->method('__invoke');
                    break;
            }

            $strategies[] = $strategy;
        }

        $createReleaseText = new AppendingCreateReleaseTextAggregate($strategies);

        $expected = <<< 'END'
            STRATEGY 0
            
            -----
            
            STRATEGY 2
            
            -----
            
            STRATEGY 4
            END;

        $this->assertSame(
            $expected,
            $createReleaseText->__invoke(
                $this->milestone,
                $this->repositoryName,
                $this->version,
                $this->sourceBranch,
                $this->repositoryPath
            )
        );
    }
}
