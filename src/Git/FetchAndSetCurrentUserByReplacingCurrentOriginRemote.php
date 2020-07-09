<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Environment\EnvironmentVariables;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

final class FetchAndSetCurrentUserByReplacingCurrentOriginRemote implements Fetch
{
    private EnvironmentVariables $variables;

    public function __construct(EnvironmentVariables $variables)
    {
        $this->variables = $variables;
    }

    public function __invoke(
        UriInterface $repositoryUri,
        string $repositoryRootDirectory
    ): void {
        (new Process(['git', 'remote', 'rm', 'origin'], $repositoryRootDirectory))
            ->run();

        (new Process(['git', 'remote', 'add', 'origin', $repositoryUri->__toString()], $repositoryRootDirectory))
            ->mustRun();

        (new Process(['git', 'fetch', 'origin'], $repositoryRootDirectory))
            ->mustRun();

        (new Process(['git', 'config', 'user.email', $this->variables->gitAuthorEmail()], $repositoryRootDirectory))
            ->mustRun();

        (new Process(['git', 'config', 'user.name', $this->variables->gitAuthorName()], $repositoryRootDirectory))
            ->mustRun();
    }
}
