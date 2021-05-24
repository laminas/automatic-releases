<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Environment\Contracts\GithubVariablesInterface;
use Laminas\AutomaticReleases\Environment\Traits\EnvTrait;

/** @psalm-immutable */
class GithubEnvironmentVariables implements GithubVariablesInterface
{
    use EnvTrait;

    /** @psalm-var non-empty-string */
    private string $accessToken;
    /** @psalm-var non-empty-string */
    private string $eventPath;
    /** @psalm-var non-empty-string */
    private string $workspacePath;

    /**
     * @psalm-param non-empty-string $accessToken
     * @psalm-param non-empty-string $eventPath
     * @psalm-param non-empty-string $workspacePath
     */
    private function __construct(
        string $accessToken,
        string $eventPath,
        string $workspacePath
    ) {
        $this->accessToken   = $accessToken;
        $this->eventPath     = $eventPath;
        $this->workspacePath = $workspacePath;
    }

    /** @psalm-return non-empty-string */
    public function accessToken(): string
    {
        return $this->accessToken;
    }

    /** @psalm-return non-empty-string */
    public function eventPath(): string
    {
        return $this->eventPath;
    }

    /** @psalm-return non-empty-string */
    public function workspacePath(): string
    {
        return $this->workspacePath;
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::getEnv('GITHUB_TOKEN'),
            self::getEnv('GITHUB_EVENT_PATH'),
            self::getEnv('GITHUB_WORKSPACE')
        );
    }

    public function githubToken(): string
    {
        return $this->accessToken();
    }

    public function githubEventPath(): string
    {
        return $this->eventPath();
    }

    public function githubWorkspacePath(): string
    {
        return $this->workspacePath();
    }
}
