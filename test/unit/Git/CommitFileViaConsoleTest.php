<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function file_put_contents;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class CommitFileViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;

    public function setUp(): void
    {
        $checkout = tempnam(sys_get_temp_dir(), 'CommitFileViaConsoleTestCheckout');
        Assert::notEmpty($checkout);

        $this->checkout = $checkout;
        unlink($this->checkout);

        (new Process(['git', 'init', $this->checkout]))
            ->mustRun();

        (new Process(
            ['git', 'symbolic-ref', 'HEAD', 'refs/heads/1.0.x'],
            $this->checkout
        ))
            ->mustRun();

        (new Process(['touch', 'README.md'], $this->checkout))
            ->mustRun();

        (new Process(['git', 'add', 'README.md'], $this->checkout))
            ->mustRun();

        (new Process(['git', 'commit', '-m', 'Initial import'], $this->checkout))
            ->mustRun();
    }

    public function testAddsAndCommitsFileProvidedWithAuthorAndCommitMessageProvided(): void
    {
        $filename = sprintf('%s/README.md', $this->checkout);
        file_put_contents(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $commitMessage = 'Commit initiated via unit test';

        (new CommitFileViaConsole())
            ->__invoke($this->checkout, BranchName::fromName('1.0.x'), 'README.md', $commitMessage);

        $commitDetails = (new Process(['git', 'show', '-1'], $this->checkout))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString($commitMessage, $commitDetails);
        self::assertStringContainsString('diff --git a/README.md b/README.md', $commitDetails);
    }
}
