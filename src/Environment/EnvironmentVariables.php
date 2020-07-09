<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Environment;

use Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use Webmozart\Assert\Assert;
use function assert;
use function getenv;
use function is_string;
use function sprintf;

/**
 * @TODO move to interface - mocking/stubbing to be done later
 *
 * @psalm-immutable
 */
class EnvironmentVariables implements Variables
{
    /** @var string */
    private $githubOrganisation;
    /** @var string */
    private $githubToken;
    /** @var string */
    private $signingSecretKey;
    /** @var string */
    private $gitAuthorName;
    /** @var string */
    private $gitAuthorEmail;
    /** @var string */
    private $githubEventPath;
    private string $workspacePath;

    private function __construct()
    {
    }

    public static function fromEnvironment(ImportGpgKeyFromString $importKey): self
    {
        $instance = new self();

        $instance->githubOrganisation = self::getenv('GITHUB_ORGANISATION'); // @TODO drop me
        $instance->githubToken        = self::getenv('GITHUB_TOKEN');
        $instance->signingSecretKey   = $importKey->__invoke(self::getenv('SIGNING_SECRET_KEY'));
        $instance->gitAuthorName      = self::getenv('GIT_AUTHOR_NAME');
        $instance->gitAuthorEmail     = self::getenv('GIT_AUTHOR_EMAIL');
        $instance->githubEventPath    = self::getenv('GITHUB_EVENT_PATH');
        $instance->workspacePath      = self::getenv('GITHUB_WORKSPACE');

        return $instance;
    }

    /** @psalm-param non-empty-string $key */
    private static function getenv(string $key): string
    {
        $value = getenv($key);

        Assert::stringNotEmpty($value, sprintf('Could not find a value for environment variable "%s"', $key));

        assert(is_string($value));

        return $value;
    }

    public function githubOrganisation(): string
    {
        return $this->githubOrganisation;
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
}
