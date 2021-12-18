<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Psr\Http\Message\UriInterface;

use function Psl\Filesystem\create_directory;
use function Psl\Filesystem\delete_file;
use function Psl\Shell\execute;
use function Psl\Str\trim;
use function Psl\Type\non_empty_string;

/**
 * @covers \Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote
 * @psalm-suppress MissingConstructor
 */
final class FetchAndSetCurrentUserByReplacingCurrentOriginRemoteTest extends TestCase
{
    private Variables $environment;
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment = $this->createMock(Variables::class);

        $source      = $this->createTemporaryFile('FetchSource');
        $destination = $this->createTemporaryFile('FetchDestination');

        non_empty_string()->assert($source);
        non_empty_string()->assert($destination);

        $this->source      = $source;
        $this->destination = $destination;

        delete_file($this->source);
        delete_file($this->destination);
        create_directory($this->source);

        execute('git', ['init'], $this->source);
        execute('git', ['config', 'user.email', 'me@example.com'], $this->source);
        execute('git', ['config', 'user.name', 'Just Me'], $this->source);
        execute('git', ['remote', 'add', 'origin', $this->destination], $this->source);
        execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->source);
        execute('git', ['checkout', '-b', 'initial-branch'], $this->source);
        execute('git', ['clone', $this->source, $this->destination]);
        execute('git', ['checkout', '-b', 'new-branch'], $this->source);
        execute('git', ['commit', '--allow-empty', '-m', 'another commit'], $this->source);

        $this->environment->method('gitAuthorName')->willReturn('Mr. Magoo Set');
        $this->environment->method('gitAuthorEmail')->willReturn('magoo-set@example.com');
    }

    public function testFetchesAndSetsCurrentUser(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->source);

        (new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($this->environment))(
            $sourceUri,
            $this->destination
        );

        self::assertSame(
            'Mr. Magoo Set',
            trim(
                execute('git', ['config', '--get', 'user.name'], $this->destination)
            )
        );
        self::assertSame(
            'magoo-set@example.com',
            trim(
                execute('git', ['config', '--get', 'user.email'], $this->destination)
            )
        );

        $fetchedBranches = execute('git', ['branch', '-r'], $this->destination);

        self::assertStringContainsString('origin/initial-branch', $fetchedBranches);
        self::assertStringContainsString('origin/new-branch', $fetchedBranches);
    }

    public function testFetchesAndSetsCurrentUserWithoutOrigin(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->source);

        execute('git', ['remote', 'rm', 'origin'], $this->destination);

        (new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($this->environment))(
            $sourceUri,
            $this->destination
        );

        self::assertSame(
            'Mr. Magoo Set',
            trim(
                execute('git', ['config', '--get', 'user.name'], $this->destination)
            )
        );
        self::assertSame(
            'magoo-set@example.com',
            trim(
                execute('git', ['config', '--get', 'user.email'], $this->destination)
            )
        );

        $fetchedBranches = execute('git', ['branch', '-r'], $this->destination);

        self::assertStringContainsString('origin/initial-branch', $fetchedBranches);
        self::assertStringContainsString('origin/new-branch', $fetchedBranches);
    }
}
