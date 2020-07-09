<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Symfony\Component\Process\Process;
use function uniqid;

final class PushViaConsole implements Push
{
    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ): void {
        if ($alias === null) {
            (new Process(['git', 'push', 'origin', $symbol], $repositoryDirectory))
                ->mustRun();

            return;
        }

        $localTemporaryBranch = uniqid('temporary-branch', true);

        (new Process(['git', 'branch', $localTemporaryBranch, $symbol], $repositoryDirectory))
            ->mustRun();

        (new Process(['git', 'push', 'origin', $localTemporaryBranch . ':' . $alias], $repositoryDirectory))
            ->mustRun();

        (new Process(['git', 'branch', '-D', $localTemporaryBranch], $repositoryDirectory))
            ->mustRun();
    }
}
