<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Environment\Contracts\TwitterVariablesInterface;
use Laminas\AutomaticReleases\Environment\Traits\EnvTrait;

/** @psalm-immutable */
class TwitterEnvironmentVariables implements TwitterVariablesInterface
{
    use EnvTrait;

    /** @psalm-var non-empty-string */
    private string $accessToken;
    /** @psalm-var non-empty-string */
    private string $accessTokenSecret;
    /** @psalm-var non-empty-string */
    private string $consumerApiKey;
    /** @psalm-var non-empty-string */
    private string $consumerApiSecret;

    /**
     * @psalm-param non-empty-string $consumerApiKey
     * @psalm-param non-empty-string $consumerApiSecret
     * @psalm-param non-empty-string $accessToken
     * @psalm-param non-empty-string $accessTokenSecret
     */
    private function __construct(
        string $accessToken,
        string $accessTokenSecret,
        string $consumerApiKey,
        string $consumerApiSecret
    ) {
        $this->accessToken       = $accessToken;
        $this->accessTokenSecret = $accessTokenSecret;
        $this->consumerApiKey    = $consumerApiKey;
        $this->consumerApiSecret = $consumerApiSecret;
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::getEnv('TWITTER_ACCESS_TOKEN'),
            self::getEnv('TWITTER_ACCESS_TOKEN_SECRET'),
            self::getEnv('TWITTER_CONSUMER_API_KEY'),
            self::getEnv('TWITTER_CONSUMER_API_SECRET')
        );
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function accessTokenSecret(): string
    {
        return $this->accessTokenSecret;
    }

    public function consumerApiKey(): string
    {
        return $this->consumerApiKey;
    }

    public function consumerApiSecret(): string
    {
        return $this->consumerApiSecret;
    }
}
