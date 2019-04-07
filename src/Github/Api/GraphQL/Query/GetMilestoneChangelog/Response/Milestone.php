<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

final class Milestone
{
    /** @var int */
    private $number;

    /** @var bool */
    private $closed;

    /** @var string */
    private $title;

    /** @var string|null */
    private $description;

    /** @var array<int, IssueOrPullRequest> */
    private $entries;

    /** @var Uri */
    private $url;

    private function __construct()
    {
    }

    public static function make(array $payload) : self
    {
        Assert::that($payload)
              ->keyExists('number')
              ->keyExists('closed')
              ->keyExists('title')
              ->keyExists('description')
              ->keyExists('issues')
              ->keyExists('pullRequests')
              ->keyExists('url');

        Assert::that($payload['number'])
              ->integer()
              ->greaterThan(0);

        Assert::that($payload['closed'])
              ->boolean();

        Assert::that($payload['title'])
              ->string()
              ->notEmpty();

        Assert::that($payload['description'])
              ->nullOr()
              ->string();

        Assert::that($payload['issues'])
              ->isArray()
              ->keyExists('nodes');

        Assert::that($payload['pullRequests'])
              ->isArray()
              ->keyExists('nodes');

        Assert::that($payload['issues']['nodes'])
              ->isArray();

        Assert::that($payload['pullRequests']['nodes'])
              ->isArray();

        Assert::that($payload['url'])
              ->string()
              ->notEmpty();

        $instance = new self();

        $instance->number      = $payload['number'];
        $instance->closed      = $payload['closed'];
        $instance->title       = $payload['title'];
        $instance->description = $payload['description'];
        $instance->url         = new Uri($payload['url']);

        $instance->entries = array_merge(
            array_values(array_map([IssueOrPullRequest::class, 'make'], $payload['issues']['nodes'])),
            array_values(array_map([IssueOrPullRequest::class, 'make'], $payload['pullRequests']['nodes']))
        );

        return $instance;
    }

    public function number() : int
    {
        return $this->number;
    }

    public function closed() : bool
    {
        return $this->closed;
    }

    public function title() : string
    {
        return $this->title;
    }

    public function description() : ?string
    {
        return $this->description;
    }

    /** @return array<int, IssueOrPullRequest> */
    public function entries() : array
    {
        return $this->entries;
    }

    public function url() : UriInterface
    {
        return $this->url;
    }

    public function assertAllIssuesAreClosed() : void
    {
        Assert::thatAll(\Safe\array_combine(
            array_map(function (IssueOrPullRequest $entry) : string {
                return $entry->url()->__toString();
            }, $this->entries),
            array_map(function (IssueOrPullRequest $entry) : bool {
                return $entry->closed();
            }, $this->entries)
        ))
            ->true();
    }
}
