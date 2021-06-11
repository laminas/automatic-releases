<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Psl\Shell;

class CheckoutBranchViaConsole implements CheckoutBranchInterface
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $branchName
    ): void {
        Shell\execute('git', ['switch', $branchName->name()], $repositoryDirectory);
    }
}
