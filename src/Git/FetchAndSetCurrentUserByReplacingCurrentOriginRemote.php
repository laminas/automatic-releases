<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Psl\File;
use Psl\Filesystem;
use Psl\Shell;
use Psr\Http\Message\UriInterface;

final class FetchAndSetCurrentUserByReplacingCurrentOriginRemote implements Fetch
{
    public function __construct(private readonly EnvironmentVariables $variables)
    {
    }

    public function __invoke(
        UriInterface $repositoryUri,
        UriInterface $uriWithCredentials,
        string $repositoryRootDirectory,
    ): void {
        Shell\execute('git', ['config', '--global', '--add', 'safe.directory', '*'], $repositoryRootDirectory);

        try {
            Shell\execute('git', ['remote', 'rm', 'origin'], $repositoryRootDirectory);
        } catch (Shell\Exception\FailedExecutionException) {
        }

        $credentialStore = Filesystem\create_temporary_file();

        Shell\execute('git', ['config', 'credential.helper', 'store --file=' . $credentialStore], $repositoryRootDirectory);
        File\write($credentialStore, $uriWithCredentials->__toString());
        Shell\execute('git', ['remote', 'add', 'origin', $repositoryUri->__toString()], $repositoryRootDirectory);
        Shell\execute('git', ['fetch', 'origin'], $repositoryRootDirectory);
        Shell\execute('git', ['config', 'user.email', $this->variables->gitAuthorEmail()], $repositoryRootDirectory);
        Shell\execute('git', ['config', 'user.name', $this->variables->gitAuthorName()], $repositoryRootDirectory);
    }
}
