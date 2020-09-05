<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use DateTimeImmutable;
use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelogViaKeepAChangelog;
use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\FrozenClock;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function array_slice;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
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
    private SecretKeyId $key;

    protected function setUp(): void
    {
        $this->clock          = new FrozenClock(new DateTimeImmutable('2020-08-05T00:00:01Z'));
        $this->checkoutBranch = $this->createMock(CheckoutBranch::class);
        $this->commitFile     = $this->createMock(CommitFile::class);
        $this->push           = $this->createMock(Push::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        $this->releaseChangelog = new CommitReleaseChangelogViaKeepAChangelog(
            new ChangelogExistsViaConsole(),
            $this->checkoutBranch,
            $this->commitFile,
            $this->push,
            $this->logger
        );

        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(file_get_contents(__DIR__ . '/../../asset/dummy-gpg-key.asc'));
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

        /** @psalm-var ChangelogReleaseNotes&MockObject $releaseNotes */
        $releaseNotes = $this->createMock(ChangelogReleaseNotes::class);
        $releaseNotes
            ->expects($this->once())
            ->method('requiresUpdatingChangelogFile')
            ->willReturn(true);
        $releaseNotes
            ->expects($this->never())
            ->method('writeChangelogFile');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $releaseNotes,
                __DIR__,
                SemVerVersion::fromMilestoneName('0.99.99'),
                BranchName::fromName('0.99.x'),
                $this->key
            )
        );
    }

    public function testNoOpWhenUnableToFindMatchingChangelogEntry(): void
    {
        $repo     = $this->createMockRepositoryWithChangelog(self::INVALID_CHANGELOG);
        $checkout = $this->checkoutMockRepositoryWithChangelog($repo);
        $branch   = BranchName::fromName('1.0.x');

        $this->checkoutBranch
            ->expects($this->never())
            ->method('__invoke');

        $this
            ->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('no changes to commit'));
        $this->commitFile->expects($this->never())->method('__invoke');
        $this->push->expects($this->never())->method('__invoke');

        /** @psalm-var ChangelogReleaseNotes&MockObject $releaseNotes */
        $releaseNotes = $this->createMock(ChangelogReleaseNotes::class);
        $releaseNotes
            ->expects($this->once())
            ->method('requiresUpdatingChangelogFile')
            ->willReturn(false);
        $releaseNotes
            ->expects($this->never())
            ->method('writeChangelogFile');

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $releaseNotes,
                $checkout,
                SemVerVersion::fromMilestoneName('1.0.0'),
                $branch,
                $this->key
            )
        );
    }

    public function testWritesCommitsAndPushesChangelogWhenFoundAndReadyToRelease(): void
    {
        $existingChangelog = sprintf(self::READY_CHANGELOG, 'TBD');
        $expectedChangelog = sprintf(self::READY_CHANGELOG, $this->clock->now()->format('Y-m-d'));
        $expectedChangelog = sprintf(
            <<< 'CHANGELOG'
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
                
                CHANGELOG,
            $this->clock->now()->format('Y-m-d')
        );
        $repositoryPath    = $this->createMockRepositoryWithChangelog($existingChangelog);
        $checkout          = $this->checkoutMockRepositoryWithChangelog($repositoryPath);
        $sourceBranch      = BranchName::fromName('1.0.x');

        Assert::stringNotEmpty($expectedChangelog);

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with($checkout, $sourceBranch);

        $changelogEntry           = new ChangelogEntry();
        $changelogEntry->contents = $existingChangelog;
        $changelogEntry->index    = 4;
        $changelogEntry->length   = 22;
        $releaseNotes             = new ChangelogReleaseNotes($expectedChangelog, $changelogEntry);

        $this->commitFile
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($checkout),
                $this->equalTo($sourceBranch),
                $this->equalTo('CHANGELOG.md'),
                $this->stringContains('1.0.0 readiness')
            );

        $this->push
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($checkout),
                $this->equalTo('1.0.x'),
            );

        self::assertNull(
            $this->releaseChangelog->__invoke(
                $releaseNotes,
                $checkout,
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x'),
                $this->key
            )
        );

        $changelogFile = $checkout . '/CHANGELOG.md';
        $contents      = file_get_contents($changelogFile);
        $this->assertStringContainsString(
            implode(
                "\n",
                array_slice(
                    explode("\n", self::READY_CHANGELOG),
                    0,
                    4
                )
            ),
            $contents
        );
        $this->assertStringContainsString($expectedChangelog, $contents);
        $this->assertStringNotContainsString($existingChangelog, $contents);
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
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $repo))->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $repo))->mustRun();
        (new Process(['git', 'add', '.'], $repo))->mustRun();
        (new Process(['git', 'commit', '-m', 'Initial import'], $repo))->mustRun();
        (new Process(['git', 'switch', '-c', '1.0.x'], $repo))->mustRun();

        return $repo;
    }

    /**
     * @psalm-param non-empty-string $origin
     * @psalm-return non-empty-string
     */
    private function checkoutMockRepositoryWithChangelog(string $origin): string
    {
        $repo = tempnam(sys_get_temp_dir(), 'CreateReleaseTextViaKeepAChangelog');
        Assert::notEmpty($repo);
        unlink($repo);

        (new Process(['git', 'clone', $origin, $repo]))->mustRun();

        return $repo;
    }

    private const INVALID_CHANGELOG = <<< 'END'
        # NOT A CHANGELOG

        This file is not a changelog.

        ## Bad headers

        It contains bad headers, among other things.

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
