<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ReleaseChangelogAndFetchContents;
use Laminas\AutomaticReleases\Changelog\ReleaseChangelogAndFetchContentsAggregate;
use Laminas\AutomaticReleases\Changelog\ReleaseChangelogEvent;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReleaseChangelogAndFetchContentsAggregateTest extends TestCase
{
    private ReleaseChangelogEvent $event;

    public function setUp(): void
    {
        /** @var InputInterface&MockObject */
        $input = $this->createMock(InputInterface::class);
        /** @var OutputInterface&MockObject */
        $output = $this->createMock(OutputInterface::class);

        $this->event = new ReleaseChangelogEvent(
            $input,
            $output,
            RepositoryName::fromFullName('example/not-a-repo'),
            __DIR__,
            BranchName::fromName('2.0.x'),
            Milestone::fromPayload([
                'number'       => 1,
                'closed'       => false,
                'title'        => '2.0.0',
                'description'  => null,
                'issues'       => ['nodes' => []],
                'pullRequests' => ['nodes' => []],
                'url'          => 'https://github.com/example/not-a-repo/milestones/1',
            ]),
            SemVerVersion::fromMilestoneName('2.0.0'),
            'Author Name <author@example.com>'
        );
    }

    public function testReturnsChangelogFromFirstStrategyReturningNonNullChangelog(): void
    {
        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy1 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy1
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn(null);

        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy2 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy2
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn('# A Changelog');

        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy3 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy3
            ->expects($this->never())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn('# Another Changelog');

        $aggregate = new ReleaseChangelogAndFetchContentsAggregate([
            $strategy1,
            $strategy2,
            $strategy3,
        ]);

        $this->assertSame('# A Changelog', $aggregate($this->event));
    }

    public function testProvidesFallbackChangelogIfNoStrategyReturnsAChangelog(): void
    {
        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy1 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy1
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn(null);

        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy2 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy2
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn(null);

        /** @var ReleaseChangelogAndFetchContents&MockObject */
        $strategy3 = $this->createMock(ReleaseChangelogAndFetchContents::class);
        $strategy3
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->equalTo($this->event))
            ->willReturn(null);

        $aggregate = new ReleaseChangelogAndFetchContentsAggregate([
            $strategy1,
            $strategy2,
            $strategy3,
        ]);

        $changelog = $aggregate($this->event);

        $this->assertStringContainsString('example/not-a-repo 2.0.0', $changelog);
    }
}
