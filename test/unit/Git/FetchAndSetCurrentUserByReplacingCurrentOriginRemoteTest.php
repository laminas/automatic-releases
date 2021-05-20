<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;
use Psr\Http\Message\UriInterface;

/** @covers \Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote */
final class FetchAndSetCurrentUserByReplacingCurrentOriginRemoteTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;
    /** @var EnvironmentVariables&MockObject */
    private EnvironmentVariables $variables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables = $this->createMock(EnvironmentVariables::class);

        $source      = Filesystem\create_temporary_file(Env\temp_dir(), 'FetchSource');
        $destination = Filesystem\create_temporary_file(Env\temp_dir(), 'FetchDestination');

        Type\non_empty_string()->assert($source);
        Type\non_empty_string()->assert($destination);

        $this->source      = $source;
        $this->destination = $destination;

        Filesystem\delete_file($this->source);
        Filesystem\delete_file($this->destination);
        Filesystem\create_directory($this->source);

        Shell\execute('git', ['init'], $this->source);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $this->source);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $this->source);
        Shell\execute('git', ['remote', 'add', 'origin', $this->destination], $this->source);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->source);
        Shell\execute('git', ['checkout', '-b', 'initial-branch'], $this->source);
        Shell\execute('git', ['clone', $this->source, $this->destination]);
        Shell\execute('git', ['checkout', '-b', 'new-branch'], $this->source);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'another commit'], $this->source);

        $this->variables->method('gitAuthorName')->willReturn('Mr. Magoo Set');
        $this->variables->method('gitAuthorEmail')->willReturn('magoo-set@example.com');
    }

    public function testFetchesAndSetsCurrentUser(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->source);

        (new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($this->variables))
            ->__invoke($sourceUri, $this->destination);

        self::assertSame(
            'Mr. Magoo Set',
            Str\trim(Shell\execute('git', ['config', '--get', 'user.name'], $this->destination))
        );
        self::assertSame(
            'magoo-set@example.com',
            Str\trim(Shell\execute('git', ['config', '--get', 'user.email'], $this->destination))
        );

        $fetchedBranches = Shell\execute('git', ['branch', '-r'], $this->destination);

        self::assertStringContainsString('origin/initial-branch', $fetchedBranches);
        self::assertStringContainsString('origin/new-branch', $fetchedBranches);
    }

    public function testFetchesAndSetsCurrentUserWithoutOrigin(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->source);

        Shell\execute('git', ['remote', 'rm', 'origin'], $this->destination);

        (new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($this->variables))
            ->__invoke($sourceUri, $this->destination);

        self::assertSame(
            'Mr. Magoo Set',
            Str\trim(Shell\execute('git', ['config', '--get', 'user.name'], $this->destination))
        );
        self::assertSame(
            'magoo-set@example.com',
            Str\trim(Shell\execute('git', ['config', '--get', 'user.email'], $this->destination))
        );

        $fetchedBranches = Shell\execute('git', ['branch', '-r'], $this->destination);

        self::assertStringContainsString('origin/initial-branch', $fetchedBranches);
        self::assertStringContainsString('origin/new-branch', $fetchedBranches);
    }
}
