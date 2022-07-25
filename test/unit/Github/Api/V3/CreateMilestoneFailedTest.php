<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\V3;

use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneFailed */
final class CreateMilestoneFailedTest extends TestCase
{
    public function test(): void
    {
        $exception = CreateMilestoneFailed::forVersion('1.2.3');

        self::assertSame('Milestone "1.2.3" creation failed', $exception->getMessage());
    }
}
