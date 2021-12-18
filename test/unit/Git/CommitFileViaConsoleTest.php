<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Psl\Exception\InvariantViolationException;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;
use function Psl\Filesystem\read_file;
use function Psl\Filesystem\write_file;
use function Psl\Shell\execute;
use function Psl\Str\format;
use function Psl\Type\non_empty_string;

final class CommitFileViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;
    private SecretKeyId $secretKeyId;

    public function setUp(): void
    {
        $this->secretKeyId = (new ImportGpgKeyFromStringViaTemporaryFile())(
            read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc')
        );

        $checkout = non_empty_string()
            ->assert(
                create_temporary_file(
                    temp_dir(),
                    'CommitFileViaConsoleTestCheckout'
                )
            );

        $this->checkout = $checkout;

        delete_file($this->checkout);
        create_directory($this->checkout);

        execute('git', ['init'], $this->checkout);
        execute('git', ['config', 'user.email', 'me@example.com'], $this->checkout);
        execute('git', ['config', 'user.name', 'Just Me'], $this->checkout);
        execute('git', ['symbolic-ref', 'HEAD', 'refs/heads/1.0.x'], $this->checkout);
        execute('touch', ['README.md'], $this->checkout);
        execute('git', ['add', 'README.md'], $this->checkout);
        execute('git', ['commit', '-m', 'Initial import'], $this->checkout);
    }

    public function testAddsAndCommitsFileProvidedWithAuthorAndCommitMessageProvided(): void
    {
        $filename = format('%s/README.md', $this->checkout);
        write_file(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $commitMessage = 'Commit initiated via unit test';

        (new CommitFileViaConsole())(
            $this->checkout,
            BranchName::fromName('1.0.x'),
            'README.md',
            $commitMessage,
            $this->secretKeyId
        );

        $commitDetails = execute('git', ['show', '-1', '--pretty=raw'], $this->checkout);

        self::assertStringContainsString($commitMessage, $commitDetails);
        self::assertStringContainsString('diff --git a/README.md b/README.md', $commitDetails);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $commitDetails);
    }

    public function testFailsIfNotOnCorrectBranch(): void
    {
        execute('git', ['switch', '-c', '1.1.x'], $this->checkout);

        $filename = format('%s/README.md', $this->checkout);

        write_file(
            $filename,
            "# README\n\nThis is a test file to test commits from laminas/automatic-releases."
        );

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('CommitFile: Cannot commit file README.md to branch 1.0.x, as a different branch is currently checked out (1.1.x).');

        (new CommitFileViaConsole())(
            $this->checkout,
            BranchName::fromName('1.0.x'),
            'README.md',
            'commit message',
            $this->secretKeyId
        );
    }
}
