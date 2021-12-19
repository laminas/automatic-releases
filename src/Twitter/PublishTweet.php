<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;
use Laminas\AutomaticReleases\Announcement\Contracts\Announcement;
use Laminas\AutomaticReleases\Announcement\Contracts\Publish;
use Laminas\AutomaticReleases\Environment\Contracts\Variables;

use function Psl\Json\encode;
use function Psl\Str\join as strJoin;

use const PHP_EOL;

final class PublishTweet implements Publish
{
    public function __construct(private TwitterOAuth $twitter, private Variables $environment)
    {
    }

    /**
     * @throws TwitterOAuthException
     */
    public function __invoke(Announcement $message): void
    {
        if (! $this->environment->twitterEnabled()) {
            // Twitter is disabled, due to missing Environment Variables.
            return;
        }

        $this->twitter->post(
            'statuses/update',
            ['status' => (string) $message]
        );

        if ($this->twitter->getLastHttpCode() !== 200) {
            throw new TwitterOAuthException(
                strJoin([
                    'Tweet: ' . $message,
                    'Status: ' . $this->twitter->getLastHttpCode(),
                    'Body: ' . encode($this->twitter->getLastBody()),
                ], PHP_EOL)
            );
        }
    }
}
