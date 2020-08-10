<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment;

use Laminas\AutomaticReleases\Gpg\SecretKeyId;

/** @psalm-immutable */
interface Variables
{
    /** @psalm-return non-empty-string */
    public function githubToken(): string;

    public function signingSecretKey(): SecretKeyId;

    /** @psalm-return non-empty-string */
    public function gitAuthorName(): string;

    /** @psalm-return non-empty-string */
    public function gitAuthorEmail(): string;

    /** @psalm-return non-empty-string */
    public function githubEventPath(): string;

    /** @psalm-return non-empty-string */
    public function githubWorkspacePath(): string;

    /** @psalm-return non-empty-string */
    public function logLevel(): string;
}
