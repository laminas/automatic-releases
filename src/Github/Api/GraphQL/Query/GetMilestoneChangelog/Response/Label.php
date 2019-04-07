<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

final class Label
{
    /** @var string */
    private $colour;

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
              ->keyExists('color')
              ->keyExists('name')
              ->keyExists('url');

        Assert::that($payload['color'])
              ->string()
              ->regex('/^[0-9a-f]{6}$/i');

        Assert::that($payload['name'])
              ->string()
              ->notEmpty();

        Assert::that($payload['url'])
              ->string()
              ->notEmpty();

        $instance = new self();

        $instance->colour = $payload['color'];
        $instance->name   = $payload['name'];
        $instance->url    = new Uri($payload['url']);

        return $instance;
    }

    public function colour() : string
    {
        return $this->colour;
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
