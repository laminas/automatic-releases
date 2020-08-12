<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersion;
use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersionViaKeepAChangelog;
use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

class BumpAndCommitChangelogVersionViaKeepAChangelogTest extends TestCase
{
    /** @var CheckoutBranch&MockObject */
    private $checkoutBranch;
    /** @var CommitFile&MockObject */
    private $commitFile;
    /** @var Push&MockObject */
    private $push;
    /** @var LoggerInterface&MockObject */
    private $logger;
    private BumpAndCommitChangelogVersionViaKeepAChangelog $bumpAndCommitChangelog;

    protected function setUp(): void
    {
        $this->checkoutBranch         = $this->createMock(CheckoutBranch::class);
        $this->commitFile             = $this->createMock(CommitFile::class);
        $this->push                   = $this->createMock(Push::class);
        $this->logger                 = $this->createMock(LoggerInterface::class);
        $this->bumpAndCommitChangelog = new BumpAndCommitChangelogVersionViaKeepAChangelog(
            $this->checkoutBranch,
            $this->commitFile,
            $this->push,
            $this->logger
        );
    }

    public function testReturnsEarlyWhenNoChangelogFilePresent(): void
    {
        $repoDir      = __DIR__;
        $sourceBranch = BranchName::fromName('1.0.x');
        $version      = SemVerVersion::fromMilestoneName('1.0.1');

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repoDir),
                $sourceBranch
            );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('No CHANGELOG.md file detected'));

        $this->assertNull(
            ($this->bumpAndCommitChangelog)(
                BumpAndCommitChangelogVersion::BUMP_PATCH,
                $repoDir,
                $version,
                $sourceBranch
            )
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
        $repoDir       = dirname($changelogFile);
        $sourceBranch  = BranchName::fromName($branchName);
        $version       = SemVerVersion::fromMilestoneName('1.0.1');

        Assert::stringNotEmpty($repoDir);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains(sprintf(
                'Bumped CHANGELOG.md to version %s in branch %s',
                $expectedVersion,
                $branchName
            )));

        $this->checkoutBranch
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repoDir),
                $sourceBranch
            );

        $this->commitFile
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->equalTo($repoDir),
                $sourceBranch,
                'CHANGELOG.md',
                $this->stringContains(sprintf(
                    'Bumps changelog version to %s',
                    $expectedVersion
                ))
            );

        $this->push
            ->expects($this->once())
            ->method('__invoke')
            ->with(
            );

        $this->assertNull(
            ($this->bumpAndCommitChangelog)(
                $bumpType,
                $repoDir,
                $version,
                $sourceBranch
            )
        );

        $changelogContents = file_get_contents($changelogFile);
        $this->assertMatchesRegularExpression(
            '/^## ' . $expectedVersion . ' - TBD$/m',
            $changelogContents,
            sprintf(
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
        $repo = tempnam(sys_get_temp_dir(), 'BumpAndCommitChangelogVersion');
        Assert::notEmpty($repo);
        unlink($repo);

        (new Process(['mkdir', '-p', $repo]))->mustRun();

        $changelogFile = sprintf('%s/CHANGELOG.md', $repo);
        Assert::stringNotEmpty($changelogFile);

        file_put_contents($changelogFile, self::CHANGELOG_STUB);

        return $changelogFile;
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
