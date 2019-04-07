<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

final class Author
{
    /** @var string */
    private $name;

    /** @var Uri */
    private $url;

    private function __construct()
    {
    }

    /** @param array<string, mixed> $payload */
    public static function make(array $payload) : self
    {
        Assert::that($payload)
              ->keyExists('login')
              ->keyExists('url');

        Assert::that($payload['login'])
              ->string()
              ->notEmpty();

        Assert::that($payload['url'])
              ->string()
              ->notEmpty();

        $instance = new self();

        $instance->name = $payload['login'];
        $instance->url  = new Uri($payload['url']);

        return $instance;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function url() : UriInterface
    {
        return $this->url;
    }
}
