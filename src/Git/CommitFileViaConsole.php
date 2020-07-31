<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Symfony\Component\Process\Process;

final class CommitFileViaConsole implements CommitFile
{
    public function __invoke(
        string $repositoryDirectory,
        string $filename,
        string $commitMessage,
        string $author
    ): void {
        (new Process(['git', 'add', $filename], $repositoryDirectory))
            ->mustRun();

        (new Process(
            ['git', 'commit', '-m', $commitMessage, sprintf('--author="%s"', $author)],
            $repositoryDirectory
        ))->mustRun();
    }
}
