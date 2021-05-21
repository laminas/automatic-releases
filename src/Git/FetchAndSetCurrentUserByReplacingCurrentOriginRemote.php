<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Psl\Shell;
use Psr\Http\Message\UriInterface;

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
        try {
            Shell\execute('git', ['remote', 'rm', 'origin'], $repositoryRootDirectory);
        } catch (Shell\Exception\FailedExecutionException) {
        }

        Shell\execute('git', ['remote', 'add', 'origin', $repositoryUri->__toString()], $repositoryRootDirectory);
        Shell\execute('git', ['fetch', 'origin'], $repositoryRootDirectory);
        Shell\execute('git', ['config', 'user.email', $this->variables->gitAuthorEmail()], $repositoryRootDirectory);
        Shell\execute('git', ['config', 'user.name', $this->variables->gitAuthorName()], $repositoryRootDirectory);
    }
}
