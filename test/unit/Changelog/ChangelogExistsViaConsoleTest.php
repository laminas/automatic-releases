<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function file_put_contents;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

class ChangelogExistsViaConsoleTest extends TestCase
{
    public function testReturnsFalseWhenChangelogIsNotPresentInBranch(): void
    {
        $repository = $this->createMockRepositoryWithChangelog();
        $workingDir = $this->checkoutMockRepositoryWithChangelog($repository);
        self::assertFalse(
            (new ChangelogExistsViaConsole($this->createMock(LoggerInterface::class)))(
                BranchName::fromName('0.99.x'),
                $workingDir
            )
        );
    }

    public function testReturnsTrueWhenChangelogIsPresentInBranch(): void
    {
        $repository = $this->createMockRepositoryWithChangelog();
        $workingDir = $this->checkoutMockRepositoryWithChangelog($repository);
        self::assertTrue(
            (new ChangelogExistsViaConsole($this->createMock(LoggerInterface::class)))(
                BranchName::fromName('1.0.x'),
                $workingDir
            )
        );
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockRepositoryWithChangelog(): string
    {
        $repo = tempnam(sys_get_temp_dir(), 'ChangelogExists');
        Assert::notEmpty($repo);
        unlink($repo);

        (new Process(['mkdir', '-p', $repo]))->mustRun();

        file_put_contents(
            sprintf('%s/%s', $repo, 'CHANGELOG.md'),
            <<< 'CHANGELOG'
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

                CHANGELOG
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
}
