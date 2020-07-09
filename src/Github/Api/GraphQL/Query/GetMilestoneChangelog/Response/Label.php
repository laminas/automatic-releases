<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;
use Zend\Diactoros\Uri;

/** @psalm-immutable */
final class Label
{
    /** @psalm-var non-empty-string */
    private string $colour;
    /** @psalm-var non-empty-string */
    private string $name;
    private UriInterface $url;

    /**
     * @psalm-param non-empty-string $colour
     * @psalm-param non-empty-string $name
     * @psalm-suppress ImpurePropertyAssignment {@see UriInterface} is pure
     */
    private function __construct(
        string $colour,
        string $name,
        UriInterface $url
    ) {
        $this->colour = $colour;
        $this->name   = $name;
        $this->url    = $url;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall the {@see UriInterface} API is pure by design
     */
    public static function fromPayload(array $payload): self
    {
        Assert::keyExists($payload, 'color');
        Assert::keyExists($payload, 'name');
        Assert::keyExists($payload, 'url');
        Assert::stringNotEmpty($payload['color']);
        Assert::regex($payload['color'], '/^[0-9a-f]{6}$/i');
        Assert::stringNotEmpty($payload['name']);
        Assert::stringNotEmpty($payload['url']);

        return new self(
            $payload['color'],
            $payload['name'],
            new Uri($payload['url'])
        );
    }

    /** @psalm-return non-empty-string */
    public function colour(): string
    {
        return $this->colour;
    }

    /** @psalm-return non-empty-string */
    public function name(): string
    {
        return $this->name;
    }

    public function url(): UriInterface
    {
        return $this->url;
    }
}
