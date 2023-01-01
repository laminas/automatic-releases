<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\HasTag;
use Laminas\AutomaticReleases\Git\HasTagViaConsole;
use PHPUnit\Framework\TestCase;

use function array_map;
use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\delete_file;
use function Psl\Shell\execute;
use function sprintf;

/** @covers \Laminas\AutomaticReleases\Git\HasTagViaConsole */
final class HasTagViaConsoleTest extends TestCase
{
    /** @var non-empty-string */
    private string $repository;
    private HasTag $hasTag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = create_temporary_file(temp_dir(), 'HasTagViaConsoleRepository');

        $this->hasTag = new HasTagViaConsole();

        delete_file($this->repository);
        create_directory($this->repository);

        execute('git', ['init'], $this->repository);

        execute('git', ['config', 'user.email', 'me@example.com'], $this->repository);
        execute('git', ['config', 'user.name', 'Just Me'], $this->repository);

        array_map(fn (string $tag) => $this->createTag($tag), [
            '1.0.0',
            '2.0.0',
        ]);
    }

    private function createTag(string $tag): void
    {
        execute('git', ['commit', '--allow-empty', '-m', 'a commit for version ' . $tag], $this->repository);
        execute('git', ['tag', '-a', $tag, '-m', 'version ' . $tag], $this->repository);
    }

    /** @param non-empty-string $repository */
    private function assertGitTagExists(string $repository, string $tag): void
    {
        self::assertTrue(($this->hasTag)($repository, $tag), sprintf('Failed asserting git tag "%s" exists.', $tag));
    }

    /** @param non-empty-string $repository */
    private function assertGitTagMissing(string $repository, string $tag): void
    {
        self::assertFalse(($this->hasTag)($repository, $tag), sprintf('Failed asserting git tag "%s" is missing.', $tag));
    }

    public function testHasTag(): void
    {
        self::assertGitTagExists($this->repository, '1.0.0');
    }

    public function testHasTagMissing(): void
    {
        self::assertGitTagMissing($this->repository, '10.0.0');
    }
}
