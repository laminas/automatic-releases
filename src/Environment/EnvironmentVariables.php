<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Environment\Traits\EnvTrait;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use function Psl\invariant;
use function Psl\Iter\contains;

/** @psalm-immutable */
class EnvironmentVariables implements Variables
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
    /** @psalm-var non-empty-string */
    private string $twitterConsumerApiKey;
    /** @psalm-var non-empty-string */
    private string $twitterConsumerApiSecret;
    /** @psalm-var non-empty-string */
    private string $twitterAaccessToken;
    /** @psalm-var non-empty-string */
    private string $twitterAccessTokenSecret;

    /**
     * @psalm-param non-empty-string $githubToken
     * @psalm-param non-empty-string $gitAuthorName
     * @psalm-param non-empty-string $gitAuthorEmail
     * @psalm-param non-empty-string $githubEventPath
     * @psalm-param non-empty-string $workspacePath
     * @psalm-param non-empty-string $logLevel
     * @psalm-param non-empty-string $twitterConsumerApiKey
     * @psalm-param non-empty-string $twitterConsumerApiSecret
     * @psalm-param non-empty-string $twitterAaccessToken
     * @psalm-param non-empty-string $twitterAccessTokenSecret
     */
    private function __construct(
        string $githubToken,
        SecretKeyId $signingSecretKey,
        string $gitAuthorName,
        string $gitAuthorEmail,
        string $githubEventPath,
        string $workspacePath,
        string $logLevel,
        string $twitterAaccessToken,
        string $twitterAccessTokenSecret,
        string $twitterConsumerApiKey,
        string $twitterConsumerApiSecret
    ) {
        $this->githubToken              = $githubToken;
        $this->signingSecretKey         = $signingSecretKey;
        $this->gitAuthorName            = $gitAuthorName;
        $this->gitAuthorEmail           = $gitAuthorEmail;
        $this->githubEventPath          = $githubEventPath;
        $this->workspacePath            = $workspacePath;
        $this->twitterConsumerApiKey    = $twitterConsumerApiKey;
        $this->twitterConsumerApiSecret = $twitterConsumerApiSecret;
        $this->twitterAaccessToken      = $twitterAaccessToken;
        $this->twitterAccessTokenSecret = $twitterAccessTokenSecret;

        /** @psalm-suppress ImpureFunctionCall the {@see \Psl\Iter\contains()} API is conditionally pure */
        invariant(
            contains(self::LOG_LEVELS, $logLevel),
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
            self::getenv('TWITTER_CONSUMER_API_KEY'),
            self::getenv('TWITTER_CONSUMER_API_SECRET'),
            self::getenv('TWITTER_ACCESS_TOKEN'),
            self::getenv('TWITTER_ACCESS_TOKEN_SECRET')
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

    public function twitterConsumerApiKey(): string
    {
        return $this->twitterConsumerApiKey;
    }

    public function twitterConsumerApiSecret(): string
    {
        return $this->twitterConsumerApiSecret;
    }

    public function twitterAccessToken(): string
    {
        return $this->twitterAaccessToken;
    }

    public function twitterAccessTokenSecret(): string
    {
        return $this->twitterAccessTokenSecret;
    }
}
