<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

use function Psl\Env\get_var;
use function Psl\invariant;
use function Psl\Iter\contains;
use function Psl\Str\format;

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

    /**
     * @psalm-param non-empty-string $githubToken
     * @psalm-param non-empty-string $gitAuthorName
     * @psalm-param non-empty-string $gitAuthorEmail
     * @psalm-param non-empty-string $githubEventPath
     * @psalm-param non-empty-string $workspacePath
     * @psalm-param non-empty-string $logLevel
     */
    private function __construct(
        private readonly string $githubToken,
        private readonly SecretKeyId $signingSecretKey,
        private readonly string $gitAuthorName,
        private readonly string $gitAuthorEmail,
        private readonly string $githubEventPath,
        private readonly string $workspacePath,
        private readonly string $logLevel,
    ) {
        /** @psalm-suppress ImpureFunctionCall the {@see \Psl\Iter\contains()} API is conditionally pure */
        invariant(
            contains(self::LOG_LEVELS, $logLevel),
            'LOG_LEVEL env MUST be a valid monolog/monolog log level constant name or value;'
            . ' see https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels',
        );
    }

    public static function fromEnvironment(ImportGpgKeyFromString $importKey): self
    {
        return new self(
            self::getenv('GITHUB_TOKEN'),
            ($importKey)(self::getenv('SIGNING_SECRET_KEY')),
            self::getenv('GIT_AUTHOR_NAME'),
            self::getenv('GIT_AUTHOR_EMAIL'),
            self::getenv('GITHUB_EVENT_PATH'),
            self::getenv('GITHUB_WORKSPACE'),
            self::getenvWithFallback(
                'LOG_LEVEL',
                self::isDebugMode() ? 'DEBUG' : 'INFO',
            ),
        );
    }

    private static function isDebugMode(): bool
    {
        return get_var('ACTIONS_RUNNER_DEBUG') !== null;
    }

    /**
     * @psalm-param  non-empty-string $key
     *
     * @psalm-return non-empty-string
     */
    private static function getenv(string $key): string
    {
        $value = get_var($key);

        invariant($value !== null && $value !== '', format('Could not find a value for environment variable "%s"', $key));

        return $value;
    }

    /**
     * @psalm-param  non-empty-string $key
     * @psalm-param  non-empty-string $default
     *
     * @psalm-return non-empty-string
     */
    private static function getenvWithFallback(string $key, string $default): string
    {
        $value = get_var($key);

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
