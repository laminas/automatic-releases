<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Event;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class MilestoneClosedEventTest extends TestCase
{
    public function testWillApplyWithValidEventTypeHeaderAndBody() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects(self::any())
            ->method('getParsedBody')
            ->willReturn(['payload' => '{"action":"closed"}']);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Github-Event')
            ->willReturn('milestone');

        self::assertTrue(MilestoneClosedEvent::appliesToRequest($request));
    }

    public function testWillNotApplyWithInvalidEventTypeHeaderAndBody() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects(self::any())
            ->method('getParsedBody')
            ->willReturn(['payload' => '{"action":"closed"}']);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Github-Event')
            ->willReturn('potato');

        self::assertFalse(MilestoneClosedEvent::appliesToRequest($request));
    }

    public function testWillNotApplyWithValidEventTypeHeaderAndInvalidBody() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects(self::any())
            ->method('getParsedBody')
            ->willReturn(['payload' => '{"action":"potato"}']);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Github-Event')
            ->willReturn('milestone');

        self::assertFalse(MilestoneClosedEvent::appliesToRequest($request));
    }

    public function testWillNotApplyWithValidEventTypeHeaderAndNoPayload() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects(self::any())
            ->method('getParsedBody')
            ->willReturn([]);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Github-Event')
            ->willReturn('milestone');

        self::assertFalse(MilestoneClosedEvent::appliesToRequest($request));
    }

    public function testFromEventJson() : void
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
