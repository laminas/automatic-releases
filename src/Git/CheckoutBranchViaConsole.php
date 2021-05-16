<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class CheckoutBranchViaConsole implements CheckoutBranch
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(
        string $repositoryDirectory,
        BranchName $branchName
    ): void {
        $this->logger->info('CheckoutBranchViaConsole: git switch {branchName}', [
            'branchName' => $branchName->name(),
        ]);
        (new Process(['git', 'switch', $branchName->name()], $repositoryDirectory))
            ->mustRun();
    }
}
