<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CreateTagViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Psr\Http\Message\UriInterface;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;
use function Psl\Filesystem\read_file;
use function Psl\Shell\execute;

/** @covers \Laminas\AutomaticReleases\Git\CreateTagViaConsole
 * @psalm-suppress MissingConstructor
 */
final class CreateTagViaConsoleTest extends TestCase
{
    private string $repository;
    private SecretKeyId $gpgKey;

    /** @noinspection MethodVisibilityInspection */
    protected function setUp(): void
    {
        parent::setUp();

        $this->gpgKey = (new ImportGpgKeyFromStringViaTemporaryFile())(
            read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc')
        );

        $this->repository = create_temporary_file(temp_dir(), 'CreateTagViaConsoleRepository');

        delete_file($this->repository);
        create_directory($this->repository);

        execute('git', ['init'], $this->repository);
        execute('git', ['config', 'user.email', 'me@example.com'], $this->repository);
        execute('git', ['config', 'user.name', 'Just Me'], $this->repository);
        execute('git', ['checkout', '-b', 'tag-branch'], $this->repository);
        execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->repository);
        execute('git', ['checkout', '-b', 'ignored-branch'], $this->repository);
        execute('git', ['commit', '--allow-empty', '-m', 'another commit'], $this->repository);
    }

    public function testCreatesSignedTag(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->repository);

        (new CreateTagViaConsole())(
            $this->repository,
            BranchName::fromName('tag-branch'),
            'name-of-the-tag',
            'changelog text for the tag',
            $this->gpgKey
        );

        execute('git', ['tag', '-v', 'name-of-the-tag'], $this->repository);

        $fetchedTag = execute('git', ['show', 'name-of-the-tag'], $this->repository);

        self::assertStringContainsString('tag name-of-the-tag', $fetchedTag);
        self::assertStringContainsString('changelog text for the tag', $fetchedTag);
        self::assertStringContainsString('a commit', $fetchedTag);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $fetchedTag);
    }
}
