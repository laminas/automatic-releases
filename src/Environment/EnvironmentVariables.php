<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl;
use Psl\Env;
use Psl\Iter;
use Psl\Str;

/** @psalm-immutable */
class EnvironmentVariables implements Variables
{
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
        Psl\invariant(
            Iter\contains(self::LOG_LEVELS, $logLevel),
            'LOG_LEVEL env MUST be a valid monolog/monolog log level constant name or value;'
            . ' see https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels'
        );

        $this->logLevel = $logLevel;
    }

    public static function fromEnvironment(ImportGpgKeyFromString $importKey): self
    {
        return new self(
            self::getenv('GITHUB_TOKEN'),
            $importKey->__invoke(self::getenv('SIGNING_SECRET_KEY')),
            self::getenv('GIT_AUTHOR_NAME'),
            self::getenv('GIT_AUTHOR_EMAIL'),
            self::getenv('GITHUB_EVENT_PATH'),
            self::getenv('GITHUB_WORKSPACE'),
            self::getenvWithFallback('LOG_LEVEL', 'INFO'),
        );
    }

    /**
     * @psalm-param  non-empty-string $key
     *
     * @psalm-return non-empty-string
     */
    private static function getenv(string $key): string
    {
        $value = Env\get_var($key);

        Psl\invariant($value !== null && $value !== '', Str\format('Could not find a value for environment variable "%s"', $key));

        return $value;
    }

    /**
     * @psalm-param  non-empty-string $default
     *
     * @psalm-return non-empty-string
     */
    private static function getenvWithFallback(string $key, string $default): string
    {
        $value = Env\get_var($key);

        return $value === null || $value === '' ? $default : $value;
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
