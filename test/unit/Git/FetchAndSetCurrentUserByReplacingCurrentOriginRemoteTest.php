<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git;

use Doctrine\AutomaticReleases\Environment\EnvironmentVariables;
use Doctrine\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;
use function trim;

/** @covers \Doctrine\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote */
final class FetchAndSetCurrentUserByReplacingCurrentOriginRemoteTest extends TestCase
{
    private string $source;
    private string $destination;
    /** @var EnvironmentVariables&MockObject */
    private EnvironmentVariables $variables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables   = $this->createMock(EnvironmentVariables::class);
        $this->source      = tempnam(sys_get_temp_dir(), 'PushViaConsoleTestSource');
        $this->destination = tempnam(sys_get_temp_dir(), 'PushViaConsoleTestDestination');

        unlink($this->source);
        unlink($this->destination);
        mkdir($this->source);

        // @TODO check if we need to set the git author and email here (will likely fail in CI)
        (new Process(['git', 'init'], $this->source))
            ->mustRun();
        (new Process(['git', 'remote', 'add', 'origin', $this->destination], $this->source))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'a commit'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'initial-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'clone', $this->source, $this->destination]))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'new-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'another commit'], $this->source))
            ->mustRun();

        $this->variables->method('gitAuthorName')
            ->willReturn('Mr. Magoo Set');
        $this->variables->method('gitAuthorEmail')
            ->willReturn('magoo-set@example.com');
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
            trim(
                (new Process(['git', 'config', '--get', 'user.name'], $this->destination))
                    ->mustRun()
                    ->getOutput()
            )
        );
        self::assertSame(
            'magoo-set@example.com',
            trim(
                (new Process(['git', 'config', '--get', 'user.email'], $this->destination))
                    ->mustRun()
                    ->getOutput()
            )
        );

        $fetchedBranches = (new Process(['git', 'branch', '-r'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('origin/initial-branch', $fetchedBranches);
        self::assertStringContainsString('origin/new-branch', $fetchedBranches);
    }
}
