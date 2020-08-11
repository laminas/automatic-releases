<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Symfony\Component\Process\Process;

class CheckoutBranchViaConsole implements CheckoutBranch
{
    public function __invoke(
        string $repositoryDirectory,
        string $branchName
    ): void {
        (new Process(['git', 'checkout', $branchName], $repositoryDirectory))
            ->mustRun();
    }
}
