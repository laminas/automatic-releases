<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment\Traits;

use function Psl\Env\get_var;
use function Psl\Env\set_var;
use function Psl\invariant;
use function Psl\Str\format;

/**
 * @psalm-immutable
 */
trait EnvTrait
{
    /**
     * @psalm-param  non-empty-string $key
     *
     * @psalm-return non-empty-string
     */
    private static function getEnv(string $key): string
    {
        $value = get_var($key);

        invariant(
            $value !== null && $value !== '',
            format('Could not find a value for environment variable "%s"', $key)
        );

        return $value;
    }

    /**
     * @psalm-param  non-empty-string $key
     * @psalm-param  non-empty-string $value
     */
    private static function setEnv(string $key, string $value): void
    {
        set_var($key, $value);
    }

    /**
     * @psalm-param  non-empty-string $default
     *
     * @psalm-return non-empty-string
     */
    private static function getEnvWithFallback(string $key, string $default): string
    {
        $value = get_var($key);

        return $value !== null && $value !== '' ? $value : $default;
    }
}
