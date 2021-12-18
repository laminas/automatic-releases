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
use Throwable;

/**
 * @covers Laminas\AutomaticReleases\Twitter\PublishTweet
 * @psalm-suppress MissingConstructor
 */
final class PublishTweetTest extends TestCase
{
    private TwitterOAuth $twitter;
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

        $this->twitter      = $this->createMock(TwitterOAuth::class);
        $this->environment  = $this->createMock(Variables::class);
        $this->tweet        = Tweet::fromMilestone($this->milestone);
        $this->publishTweet = new PublishTweet($this->twitter, $this->environment);
    }

    public function testExtractsOnlyFirstTweet(): void
    {
        self::assertSame('My first tweet.', $this->tweet->__toString());
    }

    public function testSuccessfulRequest(): void
    {
        $errors = 0;
        try {
            ($this->publishTweet)($this->tweet);
        } catch (Throwable $exception) {
            ++$errors;
        }

        self::assertSame(0, $errors, 'Error publishing a Tweet.');
    }
}
