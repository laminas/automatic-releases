<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Twitter;

use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Twitter\Value\Tweet;
use Laminas\Diactoros\Uri;
use Laminas\Twitter\Response;
use Laminas\Twitter\Twitter;
use Psr\Http\Message\UriInterface;
use stdClass;
use Webmozart\Assert\Assert;

use function sprintf;

final class CreateTweetThroughApiCall
{
    private Twitter $twitter;

    public function __construct(Twitter $twitter)
    {
        $this->twitter = $twitter;
    }

    public function __invoke(MilestoneClosedEvent $event): UriInterface
    {
        $tweet = Tweet::fromMilestoneClosedEvent($event);

        $response = $this->statusesUpdate($tweet);

        Assert::true($response->isSuccess());

        $responseJson = $response->toValue();

        Assert::isInstanceOf($responseJson, stdClass::class);
        Assert::propertyExists($responseJson, 'id');

        $tweetId = $responseJson->id;
        Assert::integer($tweetId);

        return new Uri(sprintf('https://twitter.com/i/web/status/%s', $tweetId));
    }

    private function statusesUpdate(Tweet $tweet): Response
    {
        return $this->twitter->statusesUpdate($tweet->content());
    }
}
