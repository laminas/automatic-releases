<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Psl\SecureRandom;
use Psl\Shell;

final class PushViaConsole implements Push
{
    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ): void {
        if ($alias === null) {
            Shell\execute('git', ['push', 'origin', $symbol], $repositoryDirectory);

            return;
        }

        $localTemporaryBranch = 'temporary-branch' . SecureRandom\string(8);

        Shell\execute('git', ['branch', $localTemporaryBranch, $symbol], $repositoryDirectory);
        Shell\execute('git', ['push', 'origin', $localTemporaryBranch . ':' . $alias], $repositoryDirectory);
        Shell\execute('git', ['branch', '-D', $localTemporaryBranch], $repositoryDirectory);
    }
}
