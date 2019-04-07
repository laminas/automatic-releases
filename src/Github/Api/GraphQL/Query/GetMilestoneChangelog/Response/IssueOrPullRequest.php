<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;
use function array_map;
use function array_values;

final class IssueOrPullRequest
{
    /** @var int */
    private $number;

    /** @var string */
    private $title;

    /** @var Author */
    private $author;

    /** @var array<int, Label> */
    private $labels;

    /** @var bool */
    private $closed;

    /** @var UriInterface */
    private $url;

    private function __construct()
    {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload) : self
    {
        Assert::that($payload)
              ->keyExists('number')
              ->keyExists('title')
              ->keyExists('author')
              ->keyExists('url')
              ->keyExists('closed')
              ->keyExists('labels');

        Assert::that($payload['number'])
              ->integer()
              ->greaterThan(0);

        Assert::that($payload['title'])
              ->string()
              ->notEmpty();

        Assert::that($payload['author'])
              ->isArray();

        Assert::that($payload['labels'])
              ->isArray()
              ->keyExists('nodes');

        Assert::that($payload['labels']['nodes'])
              ->isArray();

        Assert::that($payload['url'])
              ->string()
              ->notEmpty();

        Assert::that($payload['closed'])
              ->boolean();

        $instance = new self();

        $instance->number = $payload['number'];
        $instance->title  = $payload['title'];
        $instance->author = Author::fromPayload($payload['author']);
        $instance->labels = array_values(array_map([Label::class, 'fromPayload'], $payload['labels']['nodes']));
        $instance->url    = new Uri($payload['url']);
        $instance->closed = isset($payload['merged'])
            ? (bool) $payload['merged'] || $payload['closed']
            : $payload['closed'];

        return $instance;
    }

    public function number() : int
    {
        return $this->number;
    }

    public function title() : string
    {
        return $this->title;
    }

    public function author() : Author
    {
        return $this->author;
    }

    /** @return array<int, Label> */
    public function labels() : array
    {
        return $this->labels;
    }

    public function closed() : bool
    {
        return $this->closed;
    }

    public function url() : UriInterface
    {
        return $this->url;
    }
}
