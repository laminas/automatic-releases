<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use RuntimeException;

use function sprintf;

class CreateMilestoneFailed extends RuntimeException
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('Milestone "%s" creation failed', $version));
    }
}
