<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Twitter\Value;

use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Twitter\Value\Tweet;
use PHPUnit\Framework\TestCase;

final class TweetTest extends TestCase
{
    private MilestoneClosedEvent $milestoneClosedEvent;

    protected function setUp(): void
    {
        parent::setUp();
        $json                       = <<<'JSON'
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
        $this->milestoneClosedEvent = MilestoneClosedEvent::fromEventJson($json);
    }

    public function test(): void
    {
        $tweet = Tweet::fromMilestoneClosedEvent($this->milestoneClosedEvent);

        self::assertSame(
            'Released: foo/bar 1.2.3 https://github.com/foo/bar/releases/tag/1.2.3',
            $tweet->content()
        );
    }
}
