<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class ChangelogExistsViaConsole implements ChangelogExists
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        $this->logger->info('ChangelogExistsViaConsole: git show origin/{sourceBranch}:CHANGELOG.md', [
            'sourceBranch' => $sourceBranch->name(),
        ]);
        $process = new Process(['git', 'show', 'origin/' . $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);
        $process->run();

        return $process->isSuccessful();
    }
}
