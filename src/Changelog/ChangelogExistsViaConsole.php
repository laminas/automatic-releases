<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Psl\Shell;

class ChangelogExistsViaConsole implements ChangelogExists
{
    /**
     * @param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        try {
            Shell\execute('git', ['show', 'origin/' . $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);

            return true;
        } catch (Shell\Exception\FailedExecutionException) {
            return false;
        }
    }
}
