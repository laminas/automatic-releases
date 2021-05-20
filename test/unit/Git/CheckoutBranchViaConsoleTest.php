<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CheckoutBranchViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;

class CheckoutBranchViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;

    public function setUp(): void
    {
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
        Shell\execute('git', ['switch', '-c', '1.1.x'], $this->checkout);

        $this->assertBranch('1.1.x', 'Setup failed to set initial branch to 1.1.x');
    }

    public function testSwitchesToSpecifiedBranch(): void
    {
        $checkoutBranch = new CheckoutBranchViaConsole();
        $checkoutBranch($this->checkout, BranchName::fromName('1.0.x'));
        $this->assertBranch('1.0.x', 'Failed to checkout 1.0.x branch');
    }

    /** @param non-empty-string $branchName */
    private function assertBranch(string $branchName, string $message = ''): void
    {
        $output = Shell\execute('git', ['branch', '--show-current'], $this->checkout);

        self::assertEquals($branchName, Str\trim($output), $message);
    }
}
