<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CheckoutBranchViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function mkdir;
use function Safe\tempnam;
use function sys_get_temp_dir;
use function trim;
use function unlink;

class CheckoutBranchViaConsoleTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $checkout;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    public function setUp(): void
    {
        $checkout = tempnam(sys_get_temp_dir(), 'CommitFileViaConsoleTestCheckout');
        Assert::notEmpty($checkout);

        $this->checkout = $checkout;
        unlink($this->checkout);
        mkdir($this->checkout);

        (new Process(['git', 'init'], $this->checkout))
            ->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $this->checkout))
            ->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $this->checkout))
            ->mustRun();

        (new Process(
            ['git', 'symbolic-ref', 'HEAD', 'refs/heads/1.0.x'],
            $this->checkout
        ))
            ->mustRun();

        (new Process(['touch', 'README.md'], $this->checkout))
            ->mustRun();

        (new Process(['git', 'add', 'README.md'], $this->checkout))
            ->mustRun();

        (new Process(['git', 'commit', '-m', 'Initial import'], $this->checkout))
            ->mustRun();

        (new Process(['git', 'switch', '-c', '1.1.x'], $this->checkout))
            ->mustRun();

        $this->assertBranch('1.1.x', 'Setup failed to set initial branch to 1.1.x');

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testSwitchesToSpecifiedBranch(): void
    {
        $checkoutBranch = new CheckoutBranchViaConsole($this->logger);
        $checkoutBranch($this->checkout, BranchName::fromName('1.0.x'));
        $this->assertBranch('1.0.x', 'Failed to checkout 1.0.x branch');
    }

    /** @param non-empty-string $branchName */
    private function assertBranch(string $branchName, string $message = ''): void
    {
        $process = new Process(['git', 'branch', '--show-current'], $this->checkout);
        $process->run();

        self::assertTrue($process->isSuccessful(), 'git branch --show-current failed');
        $output = $process->getOutput();
        self::assertEquals($branchName, trim($output), $message);
    }
}
