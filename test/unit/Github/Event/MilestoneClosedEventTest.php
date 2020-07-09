<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Event;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;

final class MilestoneClosedEventTest extends TestCase
{
    public function testFromEventJson(): void
    {
        $json = <<<'JSON'
{
    "milestone": {
        "title": "1.2.3",
        "number": 123
    },
    "repository": {
        "full_name": "foo/bar"
    },
    "action": "closed"
}
JSON;

        $milestone = MilestoneClosedEvent::fromEventJson($json);

        self::assertSame(123, $milestone->milestoneNumber());
        self::assertEquals(SemVerVersion::fromMilestoneName('1.2.3'), $milestone->version());
        self::assertEquals(RepositoryName::fromFullName('foo/bar'), $milestone->repository());
    }
}
