<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Environment;

use Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use Webmozart\Assert\Assert;

use function getenv;
use function sprintf;

/**
 * @TODO move to interface - mocking/stubbing to be done later
 * @psalm-immutable
 */
class EnvironmentVariables implements Variables
{
    /** @psalm-var non-empty-string */
    private string $githubOrganisation;
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

    /**
     * @psalm-param non-empty-string $githubOrganisation
     * @psalm-param non-empty-string $githubToken
     * @psalm-param non-empty-string $gitAuthorName
     * @psalm-param non-empty-string $gitAuthorEmail
     * @psalm-param non-empty-string $githubEventPath
     * @psalm-param non-empty-string $workspacePath
     */
    private function __construct(
        string $githubOrganisation,
        string $githubToken,
        SecretKeyId $signingSecretKey,
        string $gitAuthorName,
        string $gitAuthorEmail,
        string $githubEventPath,
        string $workspacePath
    ) {
        $this->githubOrganisation = $githubOrganisation;
        $this->githubToken        = $githubToken;
        $this->signingSecretKey   = $signingSecretKey;
        $this->gitAuthorName      = $gitAuthorName;
        $this->gitAuthorEmail     = $gitAuthorEmail;
        $this->githubEventPath    = $githubEventPath;
        $this->workspacePath      = $workspacePath;
    }

    public static function fromEnvironment(ImportGpgKeyFromString $importKey): self
    {
        return new self(
            self::getenv('GITHUB_ORGANISATION'),
            self::getenv('GITHUB_TOKEN'),
            $importKey->__invoke(self::getenv('SIGNING_SECRET_KEY')),
            self::getenv('GIT_AUTHOR_NAME'),
            self::getenv('GIT_AUTHOR_EMAIL'),
            self::getenv('GITHUB_EVENT_PATH'),
            self::getenv('GITHUB_WORKSPACE'),
        );
    }

    /**
     * @psalm-param  non-empty-string $key
     * @psalm-return non-empty-string
     */
    private static function getenv(string $key): string
    {
        $value = getenv($key);

        Assert::stringNotEmpty($value, sprintf('Could not find a value for environment variable "%s"', $key));

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
