<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git;

use Doctrine\AutomaticReleases\Git\PushViaConsole;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** @covers \Doctrine\AutomaticReleases\Git\PushViaConsole */
final class PushViaConsoleTest extends TestCase
{
    private string $source;
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

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
        (new Process(['git', 'checkout', '-b', 'pushed-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'ignored-branch'], $this->source))
            ->mustRun();

    }

    public function testPushesSelectedGitRef(): void
    {
        (new PushViaConsole())
            ->__invoke($this->source, 'pushed-branch');

        $destinationBranches = (new Process(['git', 'branch'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }

    public function testPushesSelectedGitRefAsAlias(): void
    {
        (new PushViaConsole())
            ->__invoke($this->source, 'pushed-branch', 'pushed-alias');

        $destinationBranches = (new Process(['git', 'branch'], $this->destination))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('pushed-alias', $destinationBranches);
        self::assertStringNotContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }
}
