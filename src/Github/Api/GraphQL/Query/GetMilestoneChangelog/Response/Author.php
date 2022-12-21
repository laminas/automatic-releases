<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psl\Type;
use Psr\Http\Message\UriInterface;

final readonly class Author
{
    /** @psalm-param non-empty-string $name */
    private function __construct(
        private readonly string $name,
        private readonly UriInterface $url,
    ) {
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
