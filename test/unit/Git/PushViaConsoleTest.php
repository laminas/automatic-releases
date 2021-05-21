<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\PushViaConsole;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Type;

/** @covers \Laminas\AutomaticReleases\Git\PushViaConsole */
final class PushViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $source      = Filesystem\create_temporary_file(Env\temp_dir(), 'PushViaConsoleTestSource');
        $destination = Filesystem\create_temporary_file(Env\temp_dir(), 'PushViaConsoleTestDestination');

        $this->source      = Type\non_empty_string()->assert($source);
        $this->destination = Type\non_empty_string()->assert($destination);

        Filesystem\delete_file($this->source);
        Filesystem\delete_file($this->destination);
        Filesystem\create_directory($this->source);

        // @TODO check if we need to set the git author and email here (will likely fail in CI)
        Shell\execute('git', ['init'], $this->source);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $this->source);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $this->source);
        Shell\execute('git', ['remote', 'add', 'origin', $this->destination], $this->source);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->source);
        Shell\execute('git', ['checkout', '-b', 'initial-branch'], $this->source);
        Shell\execute('git', ['clone', $this->source, $this->destination]);
        Shell\execute('git', ['checkout', '-b', 'pushed-branch'], $this->source);
        Shell\execute('git', ['checkout', '-b', 'ignored-branch'], $this->source);
    }

    protected function tearDown(): void
    {
        $sourceBranches = Shell\execute('git', ['branch'], $this->source);

        self::assertStringNotContainsString('temporary-branch', $sourceBranches);

        parent::tearDown();
    }

    public function testPushesSelectedGitRef(): void
    {
        (new PushViaConsole())
            ->__invoke($this->source, 'pushed-branch');

        $destinationBranches = Shell\execute('git', ['branch'], $this->destination);

        self::assertStringContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }

    public function testPushesSelectedGitRefAsAlias(): void
    {
        (new PushViaConsole())
            ->__invoke($this->source, 'pushed-branch', 'pushed-alias');

        $destinationBranches = Shell\execute('git', ['branch'], $this->destination);

        self::assertStringContainsString('pushed-alias', $destinationBranches);
        self::assertStringNotContainsString('pushed-branch', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }

    public function testPushesSelectedTag(): void
    {
        Shell\execute('git', ['tag', 'tag-name', '-m', 'pushed tag'], $this->source);

        (new PushViaConsole())
            ->__invoke($this->source, 'tag-name');

        $destinationBranches = Shell\execute('git', ['tag'], $this->destination);

        self::assertStringContainsString('tag-name', $destinationBranches);
    }

    public function testPushesSelectedTagAsAliasBranch(): void
    {
        Shell\execute('git', ['tag', 'tag-name', '-m', 'pushed tag'], $this->source);

        (new PushViaConsole())
            ->__invoke($this->source, 'tag-name', 'pushed-alias');

        $destinationBranches = Shell\execute('git', ['branch'], $this->destination);

        self::assertStringContainsString('pushed-alias', $destinationBranches);
        self::assertStringNotContainsString('tag-name', $destinationBranches);
        self::assertStringNotContainsString('ignored-branch', $destinationBranches);
    }
}
