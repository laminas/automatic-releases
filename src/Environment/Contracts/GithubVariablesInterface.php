<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment\Contracts;

/** @psalm-immutable */
interface GithubVariablesInterface extends VariablesInterface
{
    /** @psalm-return non-empty-string */
    public function accessToken(): string;

    /** @psalm-return non-empty-string */
    public function eventPath(): string;

    /** @psalm-return non-empty-string */
    public function workspacePath(): string;
}