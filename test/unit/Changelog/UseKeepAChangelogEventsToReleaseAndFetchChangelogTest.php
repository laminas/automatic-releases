<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ReleaseChangelogEvent;
use Laminas\AutomaticReleases\Changelog\UseKeepAChangelogEventsToReleaseAndFetchChangelog;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use Phly\KeepAChangelog\Version\ReadyLatestChangelogEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

use function assert;
use function date;
use function file_put_contents;
use function mkdir;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class UseKeepAChangelogEventsToReleaseAndFetchChangelogTest extends TestCase
{
    /** @var CommitFile&MockObject */
    private CommitFile $commitFile;

    /** @var EventDispatcherInterface&MockObject */
    private EventDispatcherInterface $dispatcher;

    private ReleaseChangelogEvent $event;

    private Milestone $milestone;

    /** @var Push&MockObject */
    private Push $push;

    private UseKeepAChangelogEventsToReleaseAndFetchChangelog $releaseChangelog;

    /** @psalm-var non-empty-string */
    private string $repositoryDirectory;

    private RepositoryName $repositoryName;

    private BranchName $sourceBranch;

    private SemVerVersion $version;

    protected function setUp(): void
    {
        /** @psalm-var non-empty-string $repositoryDirectory */
        $repositoryDirectory = tempnam(sys_get_temp_dir(), 'UseKeepAChangelogToRelease');
        unlink($repositoryDirectory);
        mkdir($repositoryDirectory, 0777, true);

        $this->commitFile = $this->createMock(CommitFile::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->push       = $this->createMock(Push::class);
        $this->milestone  = Milestone::fromPayload([
            'number'       => 1,
            'closed'       => false,
            'title'        => '2.0.0',
            'description'  => null,
            'issues'       => ['nodes' => []],
            'pullRequests' => ['nodes' => []],
            'url'          => 'https://github.com/example/not-a-real-repository/milestones/1',
        ]);

        $this->repositoryDirectory = $repositoryDirectory;
        $this->repositoryName      = RepositoryName::fromFullName('example/not-a-repo');
        $this->sourceBranch        = BranchName::fromName('2.0.x');
        $this->version             = SemVerVersion::fromMilestoneName('2.0.0');

        $this->event = new ReleaseChangelogEvent(
            $this->repositoryName,
            $this->repositoryDirectory,
            $this->sourceBranch,
            $this->milestone,
            $this->version
        );

        $this->releaseChangelog = new UseKeepAChangelogEventsToReleaseAndFetchChangelog(
            $this->dispatcher,
            $this->commitFile,
            $this->push
        );
    }

    public function testReturnsNullWhenNoChangelogPresent(): void
    {
        $this->dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->commitFile
            ->expects($this->never())
            ->method('__invoke');

        $this->push
            ->expects($this->never())
            ->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke($this->event)
        );
    }

    public function testReturnsNullIfReadyLatestChangelogEventFails(): void
    {
        $changelogFile = sprintf('%s/CHANGELOG.md', $this->repositoryDirectory);
        file_put_contents($changelogFile, self::CHANGELOG_TEMPLATE);

        $returnedEvent = $this->createMock(ReadyLatestChangelogEvent::class);
        assert($returnedEvent instanceof ReadyLatestChangelogEvent);
        assert($returnedEvent instanceof MockObject);

        $returnedEvent
            ->expects($this->once())
            ->method('failed')
            ->willReturn(true);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ReadyLatestChangelogEvent::class))
            ->willReturn($returnedEvent);

        $this->commitFile
            ->expects($this->never())
            ->method('__invoke');

        $this->push
            ->expects($this->never())
            ->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke($this->event)
        );
    }

    public function testCommitsAndPushesChangelogWhenSuccesfullySetsDate(): void
    {
        $date             = date('Y-m-d');
        $expectedContents = sprintf(self::EXPECTED_CHANGELOG, $date);
        $changelogFile    = sprintf('%s/CHANGELOG.md', $this->repositoryDirectory);
        file_put_contents($changelogFile, self::CHANGELOG_TEMPLATE);

        $changelogEntry = $this->createMock(ChangelogEntry::class);
        assert($changelogEntry instanceof ChangelogEntry);
        assert($changelogEntry instanceof MockObject);

        $changelogEntry
            ->expects($this->once())
            ->method('contents')
            ->willReturn($expectedContents);

        $returnedEvent = $this->createMock(ReadyLatestChangelogEvent::class);
        assert($returnedEvent instanceof ReadyLatestChangelogEvent);
        assert($returnedEvent instanceof MockObject);

        $returnedEvent
            ->expects($this->once())
            ->method('failed')
            ->willReturn(false);
        $returnedEvent
            ->expects($this->once())
            ->method('changelogEntry')
            ->willReturn($changelogEntry);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ReadyLatestChangelogEvent::class))
            ->willReturn($returnedEvent);

        $this->commitFile
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($this->repositoryDirectory),
                $this->equalTo('CHANGELOG.md'),
                $this->equalTo('2.0.0 readiness')
            );

        $this->push
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($this->repositoryDirectory),
                '2.0.x'
            );

        $changelogContents = $this->releaseChangelog->__invoke($this->event);

        self::assertNotNull($changelogContents);
        self::assertStringContainsString($expectedContents, $changelogContents);
    }

    private const CHANGELOG_TEMPLATE = <<< 'END'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
        
        ## 2.0.0 - TBD
        
        ### Added
        
        - Added some stuff.
        
        ### Changed
        
        - Broke some stuff.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Removed things.
        
        ### Fixed
        
        - Nothing.

        END;

    private const EXPECTED_CHANGELOG = <<< 'END'
        ## 2.0.0 - %s
        
        ### Added
        
        - Added some stuff.
        
        ### Changed
        
        - Broke some stuff.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Removed things.
        
        ### Fixed
        
        - Nothing.

        END;
}
