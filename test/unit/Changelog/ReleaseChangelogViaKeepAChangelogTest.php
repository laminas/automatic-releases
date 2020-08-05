<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use DateTimeImmutable;
use Laminas\AutomaticReleases\Changelog\ReleaseChangelogViaKeepAChangelog;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function file_put_contents;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

class ReleaseChangelogViaKeepAChangelogTest extends TestCase
{
    private Clock $clock;

    /** @var CommitFile&MockObject */
    private CommitFile $commitFile;

    /** @var Push&MockObject */
    private Push $push;

    private ReleaseChangelogViaKeepAChangelog $releaseChangelog;

    protected function setUp(): void
    {
        $this->clock      = new FrozenClock(new DateTimeImmutable('2020-08-05T00:00:01Z'));
        $this->commitFile = $this->createMock(CommitFile::class);
        $this->push       = $this->createMock(Push::class);

        $this->releaseChangelog = new ReleaseChangelogViaKeepAChangelog(
            $this->clock,
            $this->commitFile,
            $this->push
        );
    }

    public function testNoOpWhenChangelogFileDoesNotExist(): void
    {
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                __DIR__,
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x')
            )
        );
    }

    public function testNoOpWhenUnableToFindMatchingChangelogEntry(): void
    {
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $this->createMockRepositoryWithChangelog(self::INVALID_CHANGELOG),
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x')
            )
        );
    }

    public function testNoOpWhenFailedToSetReleaseDateInChangelogEntry(): void
    {
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $this->createMockRepositoryWithChangelog(self::RELEASED_CHANGELOG),
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x')
            )
        );
    }

    public function testWritesCommitsAndPushesChangelogWhenFoundAndReadyToRelease(): void
    {
        $existingChangelog = sprintf(self::READY_CHANGELOG, 'TBD');
        $expectedChangelog = sprintf(self::READY_CHANGELOG, $this->clock->now()->format('Y-m-d'));
        $repositoryPath    = $this->createMockRepositoryWithChangelog($existingChangelog);
        $sourceBranch      = BranchName::fromName('1.0.x');

        $this->commitFile
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repositoryPath),
                $this->equalTo($sourceBranch),
                $this->equalTo('CHANGELOG.md'),
                $this->equalTo('1.0.0 readiness')
            );

        $this->push
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repositoryPath),
                $this->equalTo('1.0.x'),
            );

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $repositoryPath,
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x')
            )
        );

        $this->assertStringEqualsFile($repositoryPath . '/CHANGELOG.md', $expectedChangelog);
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockRepositoryWithChangelog(
        string $template,
        string $filename = 'CHANGELOG.md'
    ): string {
        $repo = tempnam(sys_get_temp_dir(), 'ReleaseChangelogViaKeepAChangelog');
        Assert::notEmpty($repo);
        unlink($repo);

        (new Process(['mkdir', '-p', $repo]))->mustRun();

        file_put_contents(
            sprintf('%s/%s', $repo, $filename),
            $template
        );

        return $repo;
    }

    private const INVALID_CHANGELOG = <<< 'END'
        # NOT A CHANGELOG

        This file is not a changelog.

        ## Bad headers

        It contains bad headers, among other things.

        END;

    private const RELEASED_CHANGELOG = <<< 'END'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
                
        ## 1.0.0 - 2020-01-01
        
        ### Added
        
        - Everything.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Nothing.
        
        END;

    private const READY_CHANGELOG = <<< 'END'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
                
        ## 1.0.0 - %s
        
        ### Added
        
        - Everything.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Nothing.
        
        END;
}
