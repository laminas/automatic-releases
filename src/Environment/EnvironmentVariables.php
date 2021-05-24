<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Environment\Contracts\GithubVariablesInterface;
use Laminas\AutomaticReleases\Environment\Traits\EnvTrait;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

use function Psl\invariant;
use function Psl\Iter\contains;

/** @psalm-immutable */
class EnvironmentVariables extends GithubEnvironmentVariables implements Variables, GithubVariablesInterface
{
    use EnvTrait;

    private const LOG_LEVELS = [
        '100',
        '200',
        '250',
        '300',
        '400',
        '500',
        '550',
        '600',
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
    ];

    /** @psalm-var non-empty-string */
    private string $githubToken;
    private SecretKeyId $signingSecretKey;
    /** @psalm-var non-empty-string */
    private string $gitAuthorName;
    /** @psalm-var non-empty-string */
    private string $gitAuthorEmail;
    /** @psalm-var non-empty-string */
    private string $githubEventPath;
    /** @psalm-var non-empty-string */
    private string $workspacePath;
    /** @psalm-var non-empty-string */
    private string $logLevel;

    /**
     * @psalm-param non-empty-string $githubToken
     * @psalm-param non-empty-string $gitAuthorName
     * @psalm-param non-empty-string $gitAuthorEmail
     * @psalm-param non-empty-string $githubEventPath
     * @psalm-param non-empty-string $workspacePath
     * @psalm-param non-empty-string $logLevel
     */
    private function __construct(
        string $githubToken,
        SecretKeyId $signingSecretKey,
        string $gitAuthorName,
        string $gitAuthorEmail,
        string $githubEventPath,
        string $workspacePath,
        string $logLevel
    ) {
        $this->githubToken      = $githubToken;
        $this->signingSecretKey = $signingSecretKey;
        $this->gitAuthorName    = $gitAuthorName;
        $this->gitAuthorEmail   = $gitAuthorEmail;
        $this->githubEventPath  = $githubEventPath;
        $this->workspacePath    = $workspacePath;

        /** @psalm-suppress ImpureFunctionCall the {@see \Psl\Iter\contains()} API is conditionally pure */
        invariant(
            contains(self::LOG_LEVELS, $logLevel),
            'LOG_LEVEL env MUST be a valid monolog/monolog log level constant name or value;'
            . ' see https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels'
        );

        $this->logLevel = $logLevel;
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::getEnv('GITHUB_TOKEN'),
            SecretKeyId::fromBase16String(self::getEnv('GPG_KEY_ID')),
            self::getEnv('GIT_AUTHOR_NAME'),
            self::getEnv('GIT_AUTHOR_EMAIL'),
            self::getEnv('GITHUB_EVENT_PATH'),
            self::getEnv('GITHUB_WORKSPACE'),
            self::getenvWithFallback('LOG_LEVEL', 'INFO')
        );
    }

    public static function fromEnvironmentWithGpgKey(ImportGpgKeyFromString $importKey): self
    {
        self::setEnv('GPG_KEY_ID', ($importKey)(self::getEnv('SIGNING_SECRET_KEY'))->id());

        return self::fromEnvironment();
    }

    public function githubToken(): string
    {
        return $this->githubToken;
    }

    public function signingSecretKey(): SecretKeyId
    {
        return $this->signingSecretKey;
    }

    public function gitAuthorName(): string
    {
        return $this->gitAuthorName;
    }

    public function gitAuthorEmail(): string
    {
        return $this->gitAuthorEmail;
    }

    public function githubEventPath(): string
    {
        // @TODO test me
        return $this->githubEventPath;
    }

    public function githubWorkspacePath(): string
    {
        return $this->workspacePath;
    }

    public function logLevel(): string
    {
        return $this->logLevel;
    }
}
