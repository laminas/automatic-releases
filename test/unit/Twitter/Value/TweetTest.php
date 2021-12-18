<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Twitter\Value;

use Laminas\AutomaticReleases\Announcement\Contracts\Announcement;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Laminas\AutomaticReleases\Twitter\Value\Tweet;

use function Psl\Str\format;

/** @psalm-suppress MissingConstructor */
final class TweetTest extends TestCase
{
    private Milestone $milestone;
    private Announcement $tweet;

    protected function setUp(): void
    {
        parent::setUp();
        $description     = <<<'EOT'
# Title

The description with a tweet.

``` tweet
My first tweet.
```

Other text...
EOT;
        $this->milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => '1.2.3',
            'description'  => $description,
            'issues'       => ['nodes' => []],
            'pullRequests' => ['nodes' => []],
            'url'          => 'https://github.com/vendor/project/releases/milestone/123',
        ]);

        $this->tweet = Tweet::fromMilestone($this->milestone);
    }

    public function testExtractTweet(): void
    {
        self::assertSame('My first tweet.', $this->tweet->__toString());
    }

    public function testCreateDefaultTweetFromMilestone(): void
    {
        $milestone = Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => '1.2.3',
            'description'  => 'This description has no tweets.',
            'issues'       => ['nodes' => []],
            'pullRequests' => ['nodes' => []],
            'url'          => 'https://github.com/laminas/automatic-releases/milestone/123',
        ]);
        self::assertSame(
            format(
                '%s %s',
                'Released: laminas/automatic-releases 1.2.3',
                'https://github.com/laminas/automatic-releases/releases/tag/1.2.3',
            ),
            Tweet::fromMilestone($milestone)->__toString()
        );
    }

    public function testCreateTweetFromMilestone(): void
    {
        self::assertSame('My first tweet.', Tweet::fromMilestone($this->milestone)->__toString());
    }
}
