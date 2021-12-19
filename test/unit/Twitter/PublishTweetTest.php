<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;
use Laminas\AutomaticReleases\Announcement\Contracts\Announcement;
use Laminas\AutomaticReleases\Announcement\Contracts\Publish;
use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Laminas\AutomaticReleases\Twitter\PublishTweet;
use Laminas\AutomaticReleases\Twitter\Value\Tweet;
use PHPUnit\Framework\MockObject\MockObject;
use Throwable;

use function Psl\Env\get_var;
use function Psl\Env\remove_var;
use function Psl\Env\set_var;
use function Psl\SecureRandom\string;

/**
 * @covers Laminas\AutomaticReleases\Twitter\PublishTweet
 * @psalm-suppress MissingConstructor
 */
final class PublishTweetTest extends TestCase
{
    /** @var MockObject&TwitterOAuth */
    private TwitterOAuth $twitter;
    /** @var MockObject&Variables */
    private Variables $environment;
    private Publish $publishTweet;
    private Announcement $tweet;
    private Milestone $milestone;

    /**
     * @throws TwitterOAuthException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $description     = <<<'EOT'
The description with 2 tweets.

``` tweet
My first tweet.
```


``` TWEET
My second tweet.
```
EOT;
        $this->milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => '2 tweets',
            'description'  => $description,
            'issues'       => ['nodes' => []],
            'pullRequests' => ['nodes' => []],
            'url'          => 'https://github.com/vendor/project/releases/milestone/123',
        ]);

        $this->twitter     = $this->createMock(TwitterOAuth::class);
        $this->environment = $this->createMock(Variables::class);
        $this->environment
            ->expects(self::once())
            ->method('twitterEnabled')
            ->willReturnCallback(static fn (): bool => get_var('TWITTER_ACCESS_TOKEN') !== null);

        $this->tweet        = Tweet::fromMilestone($this->milestone);
        $this->publishTweet = new PublishTweet($this->twitter, $this->environment);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetVars();
    }

    public function testSuccessfulRequest(): void
    {
        $this->resetVars();

        self::assertSame('My first tweet.', $this->tweet->__toString());

        $errors = 0;
        try {
            ($this->publishTweet)($this->tweet);
        } catch (Throwable $_exception) {
            ++$errors;
        }

        self::assertSame(0, $errors, 'Error publishing a Tweet.');
    }

    public function testTwitterOAuthException(): void
    {
        set_var('TWITTER_ACCESS_TOKEN', 'twitterAccessToken' . string(8));
        set_var('TWITTER_ACCESS_TOKEN_SECRET', 'twitterAccessTokenSecret' . string(8));
        set_var('TWITTER_CONSUMER_API_KEY', 'twitterConsumerApiKey' . string(8));
        set_var('TWITTER_CONSUMER_API_SECRET', 'twitterConsumerApiSecret' . string(8));

        $this->expectException(TwitterOAuthException::class);

        ($this->publishTweet)($this->tweet);
    }

    private function resetVars(): void
    {
        remove_var('TWITTER_ACCESS_TOKEN');
        remove_var('TWITTER_ACCESS_TOKEN_SECRET');
        remove_var('TWITTER_CONSUMER_API_KEY');
        remove_var('TWITTER_CONSUMER_API_SECRET');
    }
}
