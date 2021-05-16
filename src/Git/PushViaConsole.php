<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

use function uniqid;

final class PushViaConsole implements Push
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ): void {
        if ($alias === null) {
            $this->logger->info('PushViaConsole: git push origin {symbol}', ['symbol' => $symbol]);
            (new Process(['git', 'push', 'origin', $symbol], $repositoryDirectory))
                ->mustRun();

            return;
        }

        $localTemporaryBranch = uniqid('temporary-branch', true);

        $this->logger->info('PushViaConsole: git branch {branch} {symbol}', [
            'branch' => $localTemporaryBranch,
            'symbol' => $symbol,
        ]);
        (new Process(['git', 'branch', $localTemporaryBranch, $symbol], $repositoryDirectory))
            ->mustRun();

        $this->logger->info('PushViaConsole: git push origin {branch}:{alias}', [
            'branch' => $localTemporaryBranch,
            'alias' => $alias,
        ]);
        (new Process(['git', 'push', 'origin', $localTemporaryBranch . ':' . $alias], $repositoryDirectory))
            ->mustRun();

        $this->logger->info('PushViaConsole: git branch -D {branch}', [
            'branch' => $localTemporaryBranch,
            'alias' => $alias,
        ]);
        (new Process(['git', 'branch', '-D', $localTemporaryBranch], $repositoryDirectory))
            ->mustRun();
    }
}
