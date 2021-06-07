<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment\Contracts;

/** @psalm-immutable */
interface TwitterVariablesInterface extends VariablesInterface
{
    /** @psalm-return non-empty-string */
    public function accessToken(): string;

    /** @psalm-return non-empty-string */
    public function accessTokenSecret(): string;

    /** @psalm-return non-empty-string */
    public function consumerApiKey(): string;

    /** @psalm-return non-empty-string */
    public function consumerApiSecret(): string;
}
