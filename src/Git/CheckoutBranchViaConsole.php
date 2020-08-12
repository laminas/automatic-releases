<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Symfony\Component\Process\Process;

class CheckoutBranchViaConsole implements CheckoutBranch
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $branchName
    ): void {
        (new Process(['git', 'switch', $branchName->name()], $repositoryDirectory))
            ->mustRun();
    }
}
