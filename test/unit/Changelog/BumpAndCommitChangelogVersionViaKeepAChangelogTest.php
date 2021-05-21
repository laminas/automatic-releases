<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersionViaKeepAChangelog;
use Laminas\AutomaticReleases\Changelog\ChangelogExists;
use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;
use Psr\Log\LoggerInterface;

class BumpAndCommitChangelogVersionViaKeepAChangelogTest extends TestCase
{
    /** @var CheckoutBranch&MockObject */
    private CheckoutBranch $checkoutBranch;
    /** @var CommitFile&MockObject */
    private CommitFile $commitFile;
    /** @var Push&MockObject */
    private Push $push;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    private BumpAndCommitChangelogVersionViaKeepAChangelog $bumpAndCommitChangelog;
    private SecretKeyId $key;

    protected function setUp(): void
    {
        $this->checkoutBranch         = $this->createMock(CheckoutBranch::class);
        $this->commitFile             = $this->createMock(CommitFile::class);
        $this->push                   = $this->createMock(Push::class);
        $this->logger                 = $this->createMock(LoggerInterface::class);
        $this->bumpAndCommitChangelog = new BumpAndCommitChangelogVersionViaKeepAChangelog(
            new ChangelogExistsViaConsole(),
            $this->checkoutBranch,
            $this->commitFile,
            $this->push,
            $this->logger
        );

        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(Filesystem\read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc'));
    }

    public function testReturnsEarlyWhenNoChangelogFilePresent(): void
    {
        $repoDir      = __DIR__;
        $sourceBranch = BranchName::fromName('1.0.x');
        $version      = SemVerVersion::fromMilestoneName('1.0.1');

        $this->checkoutBranch
            ->expects(self::never())
            ->method('__invoke');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains('No CHANGELOG.md file detected'));

        ($this->bumpAndCommitChangelog)(
            BumpAndCommitChangelogVersion::BUMP_PATCH,
            $repoDir,
            $version,
            $sourceBranch,
            $this->key
        );
    }

    // phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification

    /**
     * @return iterable<
     *     string,
     *     array{
     *         0: BumpAndCommitChangelogVersion::BUMP_*,
     *         1: non-empty-string,
     *         2: non-empty-string
     *     }
     * >
     */
    public function bumpTypes(): iterable
    {
        // phpcs:enable
        yield 'bump-patch' => [BumpAndCommitChangelogVersion::BUMP_PATCH, '1.0.x', '1.0.2'];
        yield 'bump-minor' => [BumpAndCommitChangelogVersion::BUMP_MINOR, '1.1.x', '1.1.0'];
    }

    /**
     * @param BumpAndCommitChangelogVersion::BUMP_* $bumpType
     * @param non-empty-string                      $branchName
     * @param non-empty-string                      $expectedVersion
     *
     * @dataProvider bumpTypes
     */
    public function testAddsNewReleaseVersionUsingBumpTypeToChangelogFileAndCommitsAndPushes(
        string $bumpType,
        string $branchName,
        string $expectedVersion
    ): void {
        $changelogFile = $this->createMockChangelog();
        $repoDir       = Type\non_empty_string()->assert(Filesystem\get_directory($changelogFile));
        $sourceBranch  = BranchName::fromName($branchName);
        $version       = SemVerVersion::fromMilestoneName('1.0.1');

        $changelogExists = $this->createMock(ChangelogExists::class);
        $changelogExists
            ->expects(self::once())
            ->method('__invoke')
            ->with($sourceBranch, $repoDir)
            ->willReturn(true);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains(Str\format(
                'Bumped CHANGELOG.md to version %s in branch %s',
                $expectedVersion,
                $branchName
            )));

        $this->checkoutBranch
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo($repoDir),
                $sourceBranch
            );

        $this->commitFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::equalTo($repoDir),
                $sourceBranch,
                'CHANGELOG.md',
                self::stringContains(Str\format(
                    'Bumps changelog version to %s',
                    $expectedVersion
                ))
            );

        $this->push
            ->expects(self::once())
            ->method('__invoke')
            ->with();

        $bumpAndCommitChangelog = new BumpAndCommitChangelogVersionViaKeepAChangelog(
            $changelogExists,
            $this->checkoutBranch,
            $this->commitFile,
            $this->push,
            $this->logger
        );

        $bumpAndCommitChangelog(
            $bumpType,
            $repoDir,
            $version,
            $sourceBranch,
            $this->key
        );

        $changelogContents = Filesystem\read_file($changelogFile);
        self::assertMatchesRegularExpression(
            '/^## ' . $expectedVersion . ' - TBD$/m',
            $changelogContents,
            Str\format(
                'Could not locate entry for new version %s in file %s',
                $expectedVersion,
                $changelogFile
            )
        );
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockChangelog(): string
    {
        $repo = Filesystem\create_temporary_file(Env\temp_dir(), 'BumpAndCommitChangelogVersion');

        Filesystem\delete_file($repo);
        Filesystem\create_directory($repo);

        $changelogFile = Str\format('%s/CHANGELOG.md', $repo);

        Filesystem\write_file($changelogFile, self::CHANGELOG_STUB);

        Shell\execute('git', ['init', '.'], $repo);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $repo);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $repo);
        Shell\execute('git', ['add', '.'], $repo);
        Shell\execute('git', ['commit', '-m', 'Initial import'], $repo);
        Shell\execute('git', ['switch', '-c', '1.0.x'], $repo);

        return Type\non_empty_string()->assert($changelogFile);
    }

    private const CHANGELOG_STUB = <<< 'CHANGELOG'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
        
        ## 1.0.1 - 2020-08-06
        
        ### Added
        
        - Nothing.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Fixed a bug.
        CHANGELOG;
}
