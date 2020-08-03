<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function Safe\tempnam;
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
    }

    public function testAddsAndCommitsFileProvidedWithAuthorAndCommitMessageProvided(): void
    {
        $filename = sprintf('%s/README.md', $this->checkout);
        file_put_contents(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $commitMessage = 'Commit initiated via unit test';
        $author        = 'Author Name <author@example.com>';

        (new CommitFileViaConsole())
            ->__invoke($this->checkout, 'README.md', $commitMessage, $author);

        $commitDetails = (new Process(['git', 'show', '-1'], $this->checkout))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('Author: Author Name <author@example.com>', $commitDetails);
        self::assertStringContainsString('diff --git a/README.md b/README.md', $commitDetails);
    }
}
