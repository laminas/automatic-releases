<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Type\non_empty_string;

abstract class TestCase extends PHPUnitTestCase
{
    final public function createTemporaryFile(?string $prefix = null): string
    {
        /** @return non-empty-string */
        return non_empty_string()->assert(
            create_temporary_file(temp_dir(), $prefix)
        );
    }
}
