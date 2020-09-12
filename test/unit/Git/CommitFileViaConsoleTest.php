<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class CommitFileViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;
    private SecretKeyId $key;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(file_get_contents(__DIR__ . '/../../asset/dummy-gpg-key.asc'));

        $this->logger = $this->createMock(LoggerInterface::class);

        $checkout = tempnam(sys_get_temp_dir(), 'CommitFileViaConsoleTestCheckout');
        Assert::notEmpty($checkout);

        $this->checkout = $checkout;
        unlink($this->checkout);
        mkdir($this->checkout);

        (new Process(['git', 'init'], $this->checkout))
            ->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $this->checkout))
            ->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $this->checkout))
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

        (new CommitFileViaConsole($this->logger))
            ->__invoke(
                $this->checkout,
                BranchName::fromName('1.0.x'),
                'README.md',
                $commitMessage,
                $this->key
            );

        $commitDetails = (new Process(['git', 'show', '-1', '--pretty=raw'], $this->checkout))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString($commitMessage, $commitDetails);
        self::assertStringContainsString('diff --git a/README.md b/README.md', $commitDetails);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $commitDetails);
    }

    public function testFailsIfNotOnCorrectBranch(): void
    {
        (new Process(
            ['git', 'switch', '-c', '1.1.x'],
            $this->checkout
        ))
            ->mustRun();

        $filename = sprintf('%s/README.md', $this->checkout);
        file_put_contents(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different branch');
        (new CommitFileViaConsole($this->logger))
            ->__invoke(
                $this->checkout,
                BranchName::fromName('1.0.x'),
                'README.md',
                'commit message',
                $this->key
            );
    }
}
