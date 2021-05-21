<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psl;
use Psl\Regex;
use Psl\Type;
use Psr\Http\Message\UriInterface;

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
     *
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
     * @psalm-suppress ImpureMethodCall     {@see https://github.com/azjezz/psl/issues/130}
     * @psalm-suppress ImpureFunctionCall   {@see https://github.com/azjezz/psl/issues/130}
     */
    public static function fromPayload(array $payload): self
    {
        $payload = Type\shape([
            'color' => Type\non_empty_string(),
            'name' => Type\non_empty_string(),
            'url' => Type\non_empty_string(),
        ])->coerce($payload);

        Psl\invariant(Regex\matches($payload['color'], '/^[0-9a-f]{6}$/i'), 'Malformed label color.');

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
