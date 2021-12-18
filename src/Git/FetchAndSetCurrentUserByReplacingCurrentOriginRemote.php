<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Psl\Shell\Exception\FailedExecutionException;
use Psr\Http\Message\UriInterface;

use function Psl\Shell\execute;

final class FetchAndSetCurrentUserByReplacingCurrentOriginRemote implements Fetch
{
    private Variables $environment;

    public function __construct(Variables $environment)
    {
        $this->environment = $environment;
    }

    public function __invoke(
        UriInterface $repositoryUri,
        string $repositoryRootDirectory
    ): void {
        try {
            execute('git', ['remote', 'rm', 'origin'], $repositoryRootDirectory);
        } catch (FailedExecutionException) {
        }

        execute('git', ['remote', 'add', 'origin', $repositoryUri->__toString()], $repositoryRootDirectory);
        execute('git', ['fetch', 'origin'], $repositoryRootDirectory);
        execute('git', ['config', 'user.email', $this->environment->gitAuthorEmail()], $repositoryRootDirectory);
        execute('git', ['config', 'user.name', $this->environment->gitAuthorName()], $repositoryRootDirectory);
    }
}
