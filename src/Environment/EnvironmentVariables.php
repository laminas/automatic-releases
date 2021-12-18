<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

use function Psl\Env\get_var;
use function Psl\Env\set_var;
use function Psl\invariant;
use function Psl\Iter\contains;
use function Psl\Str\format;
use function Psl\Type\non_empty_string;

/** @psalm-immutable */
final class EnvironmentVariables implements Variables
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

    /** @var non-empty-string */
    private string $githubToken;
    /** @var non-empty-string */
    private string $githubEventPath;
    /** @var non-empty-string */
    private string $githubWorkspacePath;
    /** @var non-empty-string */
    private string $gitAuthorName;
    /** @var non-empty-string */
    private string $gitAuthorEmail;
    private SecretKeyId $secretKeyId;
    /** @var non-empty-string */
    private string $logLevel;
    /** @var non-empty-string */
    private string $twitterAccessToken;
    /** @var non-empty-string */
    private string $twitterAccessTokenSecret;
    /** @var non-empty-string */
    private string $twitterConsumerApiKey;
    /** @var non-empty-string */
    private string $twitterConsumerApiSecret;

    /**
     * @param non-empty-string $githubToken
     * @param non-empty-string $githubEventPath
     * @param non-empty-string $githubWorkspacePath
     * @param non-empty-string $gitAuthorName
     * @param non-empty-string $gitAuthorEmail
     * @param non-empty-string $logLevel
     * @param non-empty-string $twitterAccessToken
     * @param non-empty-string $twitterAccessTokenSecret
     * @param non-empty-string $twitterConsumerApiKey
     * @param non-empty-string $twitterConsumerApiSecret
     */
    private function __construct(
        SecretKeyId $secretKeyId,
        string $githubToken,
        string $githubEventPath,
        string $githubWorkspacePath,
        string $gitAuthorName,
        string $gitAuthorEmail,
        string $logLevel,
        string $twitterAccessToken,
        string $twitterAccessTokenSecret,
        string $twitterConsumerApiKey,
        string $twitterConsumerApiSecret
    ) {
        /** @psalm-suppress ImpureFunctionCall */
        invariant(
            contains(self::LOG_LEVELS, $logLevel),
            'LOG_LEVEL env MUST be a valid monolog/monolog log level constant name or value;'
            . ' see https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels'
        );

        $this->secretKeyId              = $secretKeyId;
        $this->githubToken              = $githubToken;
        $this->githubWorkspacePath      = $githubWorkspacePath;
        $this->githubEventPath          = $githubEventPath;
        $this->gitAuthorName            = $gitAuthorName;
        $this->gitAuthorEmail           = $gitAuthorEmail;
        $this->logLevel                 = $logLevel;
        $this->twitterAccessToken       = $twitterAccessToken;
        $this->twitterAccessTokenSecret = $twitterAccessTokenSecret;
        $this->twitterConsumerApiKey    = $twitterConsumerApiKey;
        $this->twitterConsumerApiSecret = $twitterConsumerApiSecret;
    }

    /**
     * @return non-empty-string
     */
    private static function getEnvironmentVariable(string $key): string
    {
        invariant(
            self::hasEnvironmentVariable($key),
            format('Could not find a value for environment variable "%s"', $key)
        );

        return non_empty_string()->assert(get_var($key));
    }

    private static function hasEnvironmentVariable(string $key): bool
    {
        return non_empty_string()->matches(
            get_var(non_empty_string()->assert($key))
        );
    }

    private static function setEnvironmentVariable(string $key, string $value): void
    {
        set_var(non_empty_string()->assert($key), non_empty_string()->assert($value));
    }

    /**
     * @return non-empty-string
     */
    private static function getEnvironmentVariableWithFallback(string $key, string $default): string
    {
        return self::hasEnvironmentVariable($key) ? self::getEnvironmentVariable($key) : non_empty_string()->assert($default);
    }

    public static function fromEnvironment(): self
    {
        return new self(
            SecretKeyId::fromBase16String(self::getEnvironmentVariable('GPG_KEY_ID')),
            self::getEnvironmentVariable('GITHUB_TOKEN'),
            self::getEnvironmentVariable('GITHUB_EVENT_PATH'),
            self::getEnvironmentVariable('GITHUB_WORKSPACE'),
            self::getEnvironmentVariable('GIT_AUTHOR_NAME'),
            self::getEnvironmentVariable('GIT_AUTHOR_EMAIL'),
            self::getEnvironmentVariableWithFallback('LOG_LEVEL', 'INFO'),
            self::getEnvironmentVariableWithFallback('TWITTER_ACCESS_TOKEN', self::DISABLED),
            self::getEnvironmentVariableWithFallback('TWITTER_ACCESS_TOKEN_SECRET', self::DISABLED),
            self::getEnvironmentVariableWithFallback('TWITTER_CONSUMER_API_KEY', self::DISABLED),
            self::getEnvironmentVariableWithFallback('TWITTER_CONSUMER_API_SECRET', self::DISABLED)
        );
    }

    public static function fromEnvironmentWithGpgKey(ImportGpgKeyFromString $importKey): self
    {
        self::setEnvironmentVariable('GPG_KEY_ID', (string) ($importKey)(self::getEnvironmentVariable('SIGNING_SECRET_KEY')));

        return self::fromEnvironment();
    }

    public function githubToken(): string
    {
        return $this->githubToken;
    }

    public function githubEventPath(): string
    {
        return $this->githubEventPath;
    }

    public function githubWorkspacePath(): string
    {
        return $this->githubWorkspacePath;
    }

    public function gitAuthorName(): string
    {
        return $this->gitAuthorName;
    }

    public function gitAuthorEmail(): string
    {
        return $this->gitAuthorEmail;
    }

    public function secretKeyId(): SecretKeyId
    {
        return $this->secretKeyId;
    }

    public function logLevel(): string
    {
        return $this->logLevel;
    }

    public function twitterAccessToken(): string
    {
        return $this->twitterAccessToken;
    }

    public function twitterAccessTokenSecret(): string
    {
        return $this->twitterAccessTokenSecret;
    }

    public function twitterConsumerApiKey(): string
    {
        return $this->twitterConsumerApiKey;
    }

    public function twitterConsumerApiSecret(): string
    {
        return $this->twitterConsumerApiSecret;
    }

    public function twitterEnabled(): bool
    {
        return $this->twitterAccessToken !== self::DISABLED
            && $this->twitterAccessTokenSecret !== self::DISABLED
            && $this->twitterConsumerApiKey !== self::DISABLED
            && $this->twitterConsumerApiSecret !== self::DISABLED;
    }
}
