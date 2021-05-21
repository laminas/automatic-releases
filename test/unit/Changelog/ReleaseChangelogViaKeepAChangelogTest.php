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
use Lcobucci\Clock\FrozenClock;
use Phly\KeepAChangelog\Common\ChangelogEntry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Dict;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Psr\Log\LoggerInterface;

class ReleaseChangelogViaKeepAChangelogTest extends TestCase
{
    private FrozenClock $frozenClock;

    /** @var CheckoutBranch&MockObject */
    private CheckoutBranch $checkoutBranch;

    /** @var CommitFile&MockObject */
    private CommitFile $commitFile;

    /** @var MockObject&Push */
    private Push $push;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private CommitReleaseChangelogViaKeepAChangelog $releaseChangelog;
    private SecretKeyId $key;

    protected function setUp(): void
    {
        $this->frozenClock    = new FrozenClock(new DateTimeImmutable('2020-08-05T00:00:01Z'));
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
            ->__invoke(Filesystem\read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc'));
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
        $existingChangelog = Str\format(self::READY_CHANGELOG, 'TBD');
        $expectedChangelog = Type\non_empty_string()->assert(Str\format(
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
            $this->frozenClock->now()->format('Y-m-d')
        ));
        $repositoryPath    = $this->createMockRepositoryWithChangelog($existingChangelog);
        $checkout          = $this->checkoutMockRepositoryWithChangelog($repositoryPath);
        $sourceBranch      = BranchName::fromName('1.0.x');

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
        $contents      = Filesystem\read_file($changelogFile);
        $this->assertStringContainsString(
            Str\join(
                Vec\values(Dict\take(Str\split(self::READY_CHANGELOG, "\n"), 4)),
                "\n",
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
        $repo = Type\non_empty_string()
            ->assert(Filesystem\create_temporary_file(Env\temp_dir(), 'ReleaseChangelogViaKeepAChangelog'));

        Filesystem\delete_file($repo);
        Filesystem\create_directory($repo);
        Filesystem\write_file(
            Str\format('%s/%s', $repo, $filename),
            $template
        );

        Shell\execute('git', ['init', '.'], $repo);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $repo);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $repo);
        Shell\execute('git', ['add', '.'], $repo);
        Shell\execute('git', ['commit', '-m', 'Initial import'], $repo);
        Shell\execute('git', ['switch', '-c', '1.0.x'], $repo);

        return $repo;
    }

    /**
     * @psalm-param non-empty-string $origin
     *
     * @psalm-return non-empty-string
     */
    private function checkoutMockRepositoryWithChangelog(string $origin): string
    {
        $repo = Type\non_empty_string()
            ->assert(Filesystem\create_temporary_file(Env\temp_dir(), 'CreateReleaseTextViaKeepAChangelog'));

        Filesystem\delete_file($repo);

        Shell\execute('git', ['clone', $origin, $repo]);

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
