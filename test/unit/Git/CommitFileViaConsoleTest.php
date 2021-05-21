<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Exception\InvariantViolationException;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;

final class CommitFileViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;
    private SecretKeyId $key;

    public function setUp(): void
    {
        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(Filesystem\read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc'));

        $checkout = Type\non_empty_string()
            ->assert(Filesystem\create_temporary_file(Env\temp_dir(), 'CommitFileViaConsoleTestCheckout'));

        $this->checkout = $checkout;

        Filesystem\delete_file($this->checkout);
        Filesystem\create_directory($this->checkout);

        Shell\execute('git', ['init'], $this->checkout);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $this->checkout);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $this->checkout);
        Shell\execute('git', ['symbolic-ref', 'HEAD', 'refs/heads/1.0.x'], $this->checkout);
        Shell\execute('touch', ['README.md'], $this->checkout);
        Shell\execute('git', ['add', 'README.md'], $this->checkout);
        Shell\execute('git', ['commit', '-m', 'Initial import'], $this->checkout);
    }

    public function testAddsAndCommitsFileProvidedWithAuthorAndCommitMessageProvided(): void
    {
        $filename = Str\format('%s/README.md', $this->checkout);
        Filesystem\write_file(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $commitMessage = 'Commit initiated via unit test';

        (new CommitFileViaConsole())
            ->__invoke(
                $this->checkout,
                BranchName::fromName('1.0.x'),
                'README.md',
                $commitMessage,
                $this->key
            );

        $commitDetails = Shell\execute('git', ['show', '-1', '--pretty=raw'], $this->checkout);

        self::assertStringContainsString($commitMessage, $commitDetails);
        self::assertStringContainsString('diff --git a/README.md b/README.md', $commitDetails);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $commitDetails);
    }

    public function testFailsIfNotOnCorrectBranch(): void
    {
        Shell\execute('git', ['switch', '-c', '1.1.x'], $this->checkout);

        $filename = Str\format('%s/README.md', $this->checkout);
        Filesystem\write_file($filename, "# README\n\nThis is a test file to test commits from laminas/automatic-releases.");

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('CommitFile: Cannot commit file README.md to branch 1.0.x, as a different branch is currently checked out (1.1.x).');
        (new CommitFileViaConsole())
            ->__invoke(
                $this->checkout,
                BranchName::fromName('1.0.x'),
                'README.md',
                'commit message',
                $this->key
            );
    }
}
