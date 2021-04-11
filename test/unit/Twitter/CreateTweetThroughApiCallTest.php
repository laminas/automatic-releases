<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Twitter;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Twitter\CreateTweetThroughApiCall;
use Laminas\Diactoros\Uri;
use Laminas\Http\Response as HttpResponse;
use Laminas\Json\Json;
use Laminas\Twitter\Response as TwitterResponse;
use Laminas\Twitter\Twitter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @covers Laminas\AutomaticReleases\Twitter\CreateTweetThroughApiCall */
final class CreateTweetThroughApiCallTest extends TestCase
{
    private Twitter | MockObject $twitter;
    private EnvironmentVariables | MockObject $variables;
    private CreateTweetThroughApiCall | MockObject $createTweetThroughApiCall;
    private MilestoneClosedEvent | MockObject $milestoneClosedEvent;
    private HttpResponse | MockObject $httpResponse;
    private TwitterResponse | MockObject $twitterResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variables = $this->createMock(EnvironmentVariables::class);

        $this->milestoneClosedEvent = MilestoneClosedEvent::fromEventJson(
            '{"milestone":{"title": "1.2.3","number": 123},"repository": {"full_name": "foo/bar"},"action": "closed"}'
        );

        $this->httpResponse = $this->createMock(HttpResponse::class);
        $this->httpResponse->method('getContent')
            ->willReturn('{"id":12345,"id_str":"12345"}');

        $this->twitterResponse = $this->createMock(TwitterResponse::class);
        $this->twitterResponse->method('isSuccess')->willReturn(true);
        $this->twitterResponse->method('toValue')->willReturn(Json::decode(
            (string) $this->httpResponse->getContent(),
            Json::TYPE_OBJECT
        ));

        $this->twitter = $this->createMock(Twitter::class);
        $this->twitter->method('statusesUpdate')->willReturn($this->twitterResponse);

        $this->createTweetThroughApiCall = new CreateTweetThroughApiCall($this->twitter);
    }

    public function testSuccessfulRequest(): void
    {
        $tweetUri = ($this->createTweetThroughApiCall)($this->milestoneClosedEvent);

        self::assertInstanceOf(Uri::class, $tweetUri);

        self::assertSame('https://twitter.com/i/web/status/12345', $tweetUri->__toString());
    }
}
