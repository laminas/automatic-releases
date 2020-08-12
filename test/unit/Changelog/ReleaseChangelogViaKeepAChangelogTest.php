<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use DateTimeImmutable;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelogViaKeepAChangelog;
use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    /** @var CheckoutBranch&MockObject */
    private CheckoutBranch $checkoutBranch;

    /** @var CommitFile&MockObject */
    private CommitFile $commitFile;

    /** @var Push&MockObject */
    private Push $push;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private CommitReleaseChangelogViaKeepAChangelog $releaseChangelog;

    protected function setUp(): void
    {
        $this->clock          = new FrozenClock(new DateTimeImmutable('2020-08-05T00:00:01Z'));
        $this->checkoutBranch = $this->createMock(CheckoutBranch::class);
        $this->commitFile     = $this->createMock(CommitFile::class);
        $this->push           = $this->createMock(Push::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        $this->releaseChangelog = new CommitReleaseChangelogViaKeepAChangelog(
            $this->clock,
            $this->checkoutBranch,
            $this->commitFile,
            $this->push,
            $this->logger
        );
    }

    public function testNoOpWhenChangelogFileDoesNotExist(): void
    {
        $this->checkoutBranch
            ->expects($this->never())
            ->method('__invoke');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('No CHANGELOG.md'));
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                __DIR__,
                SemVerVersion::fromMilestoneName('0.99.99'),
                BranchName::fromName('0.99.x')
            )
        );
    }

    public function testNoOpWhenUnableToFindMatchingChangelogEntry(): void
    {
        $repo   = $this->createMockRepositoryWithChangelog(self::INVALID_CHANGELOG);
        $branch = BranchName::fromName('1.0.x');

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with($repo, $branch);

        $this
            ->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Failed to find release version'));
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $repo,
                SemVerVersion::fromMilestoneName('1.0.0'),
                $branch
            )
        );
    }

    public function testNoOpWhenFailedToSetReleaseDateInChangelogEntry(): void
    {
        $repo   = $this->createMockRepositoryWithChangelog(self::RELEASED_CHANGELOG);
        $branch = BranchName::fromName('1.0.x');

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with($repo, $branch);

        $this
            ->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Failed setting release date'));
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $repo,
                SemVerVersion::fromMilestoneName('1.0.0'),
                $branch
            )
        );
    }

    public function testWritesCommitsAndPushesChangelogWhenFoundAndReadyToRelease(): void
    {
        $existingChangelog = sprintf(self::READY_CHANGELOG, 'TBD');
        $expectedChangelog = sprintf(self::READY_CHANGELOG, $this->clock->now()->format('Y-m-d'));
        $repositoryPath    = $this->createMockRepositoryWithChangelog($existingChangelog);
        $sourceBranch      = BranchName::fromName('1.0.x');

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with($repositoryPath, $sourceBranch);

        $this
            ->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Set release date'));

        $this->commitFile
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repositoryPath),
                $this->equalTo($sourceBranch),
                $this->equalTo('CHANGELOG.md'),
                $this->stringContains('1.0.0 readiness')
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

        (new Process(['git', 'init', '.'], $repo))->mustRun();
        (new Process(['git', 'add', '.'], $repo))->mustRun();
        (new Process(['git', 'commit', '-m', 'Initial import'], $repo))->mustRun();
        (new Process(['git', 'switch', '-c', '1.0.x'], $repo))->mustRun();

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
