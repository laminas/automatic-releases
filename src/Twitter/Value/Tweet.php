<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Twitter\Value;

use Laminas\AutomaticReleases\Announcement\Contracts\Announcement;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;

use function Psl\Dict\filter;
use function Psl\Dict\take;
use function Psl\Iter\first;
use function Psl\Regex\capture_groups;
use function Psl\Regex\first_match;
use function Psl\Str\Byte\split;
use function Psl\Str\Byte\trim as strTrim;
use function Psl\Str\format;
use function Psl\Type\non_empty_string;
use function Psl\Vec\filter_keys;

/** @immutable */
final class Tweet implements Announcement
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = non_empty_string()->assert($message);
    }

    public static function fromMilestone(Milestone $milestone): Announcement
    {
        return new self(strTrim(first(filter_keys(
            first_match(
                non_empty_string()->assert($milestone->description()),
                '#^[`]{3}\s?tweet(?<announcement>.*?)[`]{3}$#ims',
                capture_groups(['announcement'])
            ) ?? ['announcement' => self::defaultTweet($milestone)],
            static fn (int|string $key) => $key === 'announcement'
        ))));
    }

    public function __toString(): string
    {
        return $this->message;
    }

    private static function defaultTweet(Milestone $milestone): string
    {
        $repository = format('%s/%s', ...take(filter(
            split($milestone->url()->getPath(), '/'),
            static fn (string $string): bool => non_empty_string()->matches($string)
        ), 2));

        $version = $milestone->title();

        return format(
            'Released: %s %s https://github.com/%s/releases/tag/%s',
            $repository,
            $version,
            $repository,
            $version
        );
    }
}
