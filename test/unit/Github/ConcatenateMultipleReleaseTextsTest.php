<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\ConcatenateMultipleReleaseTexts;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function assert;
use function range;

final class ConcatenateMultipleReleaseTextsTest extends TestCase
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

    public function testIndicatesCannotCreateReleaseTextIfNoGeneratorCan(): void
    {
        /** @psalm-var non-empty-list<CreateReleaseText> $generators */
        $generators = [];
        foreach (range(0, 4) as $index) {
            $generator = $this->createMock(CreateReleaseText::class);
            assert($generator instanceof CreateReleaseText);
            assert($generator instanceof MockObject);

            $generator
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
            $generators[] = $generator;
        }

        $createReleaseText = new ConcatenateMultipleReleaseTexts($generators);

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

    public function testIndicatesCanCreateReleaseTextIfAtLeastOneGeneratorCan(): void
    {
        /** @psalm-var non-empty-list<CreateReleaseText> $generators */
        $generators = [];
        foreach (range(0, 4) as $index) {
            $generator = $this->createMock(CreateReleaseText::class);
            assert($generator instanceof CreateReleaseText);
            assert($generator instanceof MockObject);

            if ($index < 2) {
                $generator
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
                $generators[] = $generator;
                continue;
            }

            if ($index > 2) {
                $generator
                    ->expects($this->never())
                    ->method('canCreateReleaseText');
                $generators[] = $generator;
                continue;
            }

            $generator
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
            $generators[] = $generator;
        }

        $createReleaseText = new ConcatenateMultipleReleaseTexts($generators);

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

    public function testReturnsConcatenatedValuesFromGeneratorsThatCanCreateReleaseText(): void
    {
        /** @psalm-var non-empty-list<CreateReleaseText> $generators */
        $generators = [];
        foreach (range(0, 4) as $index) {
            $generator = $this->createMock(CreateReleaseText::class);
            assert($generator instanceof CreateReleaseText);
            assert($generator instanceof MockObject);

            switch ($index) {
                case 0:
                    // fall-through
                case 2:
                    // fall-through
                case 4:
                    $generator
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
                    $generator
                        ->expects($this->once())
                        ->method('__invoke')
                        ->with(
                            $this->equalTo($this->milestone),
                            $this->equalTo($this->repositoryName),
                            $this->equalTo($this->version),
                            $this->equalTo($this->sourceBranch),
                            $this->equalTo($this->repositoryPath)
                        )
                        ->willReturn('GENERATOR ' . $index);
                    break;
                default:
                    $generator
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
                    $generator
                        ->expects($this->never())
                        ->method('__invoke');
                    break;
            }

            $generators[] = $generator;
        }

        $createReleaseText = new ConcatenateMultipleReleaseTexts($generators);

        $expected = <<< 'END'
            GENERATOR 0
            
            -----
            
            GENERATOR 2
            
            -----
            
            GENERATOR 4
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
