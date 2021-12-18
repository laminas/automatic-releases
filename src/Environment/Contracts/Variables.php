<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment\Contracts;

use Laminas\AutomaticReleases\Gpg\SecretKeyId;

/** @psalm-immutable */
interface Variables
{
    public const DEFAULT  = 'NONE';
    public const DISABLED = 'DISABLED';

    public static function fromEnvironment(): self;

    public function twitterEnabled(): bool;

    /** @return non-empty-string GITHUB_TOKEN */
    public function githubToken(): string;

    /** @psalm-return non-empty-string GITHUB_EVENT_PATH */
    public function githubEventPath(): string;

    /** @psalm-return non-empty-string GITHUB_WORKSPACE */
    public function githubWorkspacePath(): string;

    /** @psalm-return non-empty-string GIT_AUTHOR_NAME */
    public function gitAuthorName(): string;

    /** @psalm-return non-empty-string GIT_AUTHOR_EMAIL */
    public function gitAuthorEmail(): string;

    public function secretKeyId(): SecretKeyId;

    /** @psalm-return non-empty-string LOG_LEVEL */
    public function logLevel(): string;

    /** @return non-empty-string TWITTER_ACCESS_TOKEN */
    public function twitterAccessToken(): string;

    /** @return non-empty-string TWITTER_ACCESS_TOKEN_SECRET */
    public function twitterAccessTokenSecret(): string;

    /** @return non-empty-string TWITTER_CONSUMER_API_KEY */
    public function twitterConsumerApiKey(): string;

    /** @return non-empty-string TWITTER_CONSUMER_API_SECRET */
    public function twitterConsumerApiSecret(): string;
}
