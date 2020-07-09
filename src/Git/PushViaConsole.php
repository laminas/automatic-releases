<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Symfony\Component\Process\Process;

final class PushViaConsole implements Push
{
    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        string $alias = null
    ) : void {
        $pushedRef = $alias !== null ? $symbol . ':' . $alias : $symbol;

        (new Process(['git', 'push', 'origin', $pushedRef], $repositoryDirectory))
            ->mustRun();
    }
}
