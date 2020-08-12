<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Symfony\Component\Process\Process;

class ChangelogExistsViaConsole implements ChangelogExists
{
    /**
     * @param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        $process = new Process(['git', 'show', $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);
        $process->run();

        return $process->isSuccessful();
    }
}
