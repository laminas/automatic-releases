<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Exception;
use Laminas\AutomaticReleases\Application\Command\TweetReleaseCommand;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Twitter\CreateTweetThroughApiCall;
use Laminas\Http\Response as HttpResponse;
use Laminas\Json\Json;
use Laminas\Twitter\Response as TwitterResponse;
use Laminas\Twitter\Twitter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class TweetReleaseCommandTest extends TestCase
{
    private TweetReleaseCommand | MockObject $command;

    private LoadCurrentGithubEvent | MockObject $loadEvent;

    private CreateTweetThroughApiCall | MockObject $createTweet;

    private MockObject | MilestoneClosedEvent $milestoneClosedEvent;

    private Twitter | MockObject $twitter;

    private HttpResponse | MockObject $httpResponse;
    private TwitterResponse | MockObject $twitterResponse;

    private ArrayInput $nullInput;
    private NullOutput $nullOutput;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadEvent = $this->createMock(LoadCurrentGithubEvent::class);

        $this->milestoneClosedEvent = MilestoneClosedEvent::fromEventJson(
            '{"milestone": {"title": "1.2.3","number": 123},"repository": {"full_name": "foo/bar"},"action": "closed"}'
        );

        $this->loadEvent->method('__invoke')
            ->willReturn($this->milestoneClosedEvent);

        $this->httpResponse = $this->createMock(HttpResponse::class);
        $this->httpResponse->method('getContent')
            ->willReturn('{"id":12345,"id_str":"12345"}');

        $this->twitterResponse = $this->createMock(TwitterResponse::class);
        $this->twitterResponse->method('isSuccess')
            ->willReturn(true);
        $this->twitterResponse->method('toValue')
            ->willReturn(Json::decode(
                (string) $this->httpResponse->getContent(),
                Json::TYPE_OBJECT
            ));

        $this->twitter = $this->createMock(Twitter::class);
        $this->twitter->method('statusesUpdate')
            ->willReturn($this->twitterResponse);

        $this->createTweet = new CreateTweetThroughApiCall($this->twitter);
        $this->command     = new TweetReleaseCommand(
            $this->loadEvent,
            $this->createTweet
        );

        $this->nullInput  = new ArrayInput([]);
        $this->nullOutput = new NullOutput();
    }

    public function testCommandName(): void
    {
        self::assertSame('laminas:automatic-releases:tweet-release', $this->command->getName());
    }

    /**
     * @throws Exception
     */
    public function testWillAnnounceRelease(): void
    {
        self::assertSame(
            0,
            $this->command->run(
                $this->nullInput,
                $this->nullOutput
            )
        );
    }
}
