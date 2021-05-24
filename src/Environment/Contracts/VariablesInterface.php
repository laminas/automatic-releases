<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Environment\Contracts;

/** @psalm-immutable */
interface VariablesInterface
{
    public static function fromEnvironment(): VariablesInterface;
}
