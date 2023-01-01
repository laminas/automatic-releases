<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CreateTagViaConsole;
use Laminas\AutomaticReleases\Git\HasTag;
use Laminas\AutomaticReleases\Git\HasTagViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psr\Http\Message\UriInterface;

use function Psl\File\read;

/** @covers \Laminas\AutomaticReleases\Git\CreateTagViaConsole */
final class CreateTagViaConsoleTest extends TestCase
{
    /** @var non-empty-string */
    private string $repository;
    private SecretKeyId $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(read(__DIR__ . '/../../asset/dummy-gpg-key.asc'));

        $this->repository = Filesystem\create_temporary_file(Env\temp_dir(), 'CreateTagViaConsoleRepository');

        Filesystem\delete_file($this->repository);
        Filesystem\create_directory($this->repository);

        Shell\execute('git', ['init'], $this->repository);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $this->repository);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $this->repository);
        Shell\execute('git', ['checkout', '-b', 'tag-branch'], $this->repository);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->repository);
        Shell\execute('git', ['checkout', '-b', 'ignored-branch'], $this->repository);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'another commit'], $this->repository);
    }

    public function testCreatesSignedTag(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);
        $sourceUri->method('__toString')->willReturn($this->repository);

        $hasTag = $this->createMock(HasTag::class);
        $hasTag->method('__invoke')
            ->with($this->repository, 'name-of-the-tag')
            ->willReturn(false);

        (new CreateTagViaConsole($hasTag))(
            $this->repository,
            BranchName::fromName('tag-branch'),
            'name-of-the-tag',
            'changelog text for the tag',
            $this->key,
        );

        Shell\execute('git', ['tag', '-v', 'name-of-the-tag'], $this->repository);

        $fetchedTag = Shell\execute('git', ['show', 'name-of-the-tag'], $this->repository);

        self::assertStringContainsString('tag name-of-the-tag', $fetchedTag);
        self::assertStringContainsString('changelog text for the tag', $fetchedTag);
        self::assertStringContainsString('a commit', $fetchedTag);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $fetchedTag);
    }

    public function testSkipsIfTagAlreadyExists(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);
        $sourceUri->method('__toString')->willReturn($this->repository);

        $hasTag = new HasTagViaConsole();

        (new CreateTagViaConsole($hasTag))(
            $this->repository,
            BranchName::fromName('tag-branch'),
            'name-of-the-tag',
            'changelog text for the tag',
            $this->key,
        );

        Shell\execute('git', ['tag', '-v', 'name-of-the-tag'], $this->repository);
        $fetchedTag = Shell\execute('git', ['show', 'name-of-the-tag'], $this->repository);

        self::assertStringContainsString('tag name-of-the-tag', $fetchedTag);
        self::assertStringContainsString('changelog text for the tag', $fetchedTag);
        self::assertStringContainsString('a commit', $fetchedTag);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $fetchedTag);

        (new CreateTagViaConsole($hasTag))(
            $this->repository,
            BranchName::fromName('tag-branch'),
            'name-of-the-tag',
            'changelog text for the tag',
            $this->key,
        );
    }
}
