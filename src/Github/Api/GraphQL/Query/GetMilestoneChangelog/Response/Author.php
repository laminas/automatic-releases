<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psl\Type;
use Psr\Http\Message\UriInterface;

final class Author
{
    /** @psalm-var non-empty-string */
    private string $name;
    private UriInterface $url;

    /** @psalm-param non-empty-string $name */
    private function __construct(string $name, UriInterface $url)
    {
        $this->name = $name;
        $this->url  = $url;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $payload = Type\shape([
            'login' => Type\non_empty_string(),
            'url' => Type\non_empty_string(),
        ])->coerce($payload);

        return new self($payload['login'], new Uri($payload['url']));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(): UriInterface
    {
        return $this->url;
    }
}
