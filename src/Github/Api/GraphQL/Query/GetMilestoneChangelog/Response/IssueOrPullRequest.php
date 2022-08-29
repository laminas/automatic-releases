<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psl\Type;
use Psl\Vec;
use Psr\Http\Message\UriInterface;

/** @psalm-immutable */
final class IssueOrPullRequest
{
    /**
     * @param array<int, Label> $labels
     * @psalm-param non-empty-string $title
     * @psalm-param list<Label> $labels
     */
    private function __construct(
        private readonly int $number,
        private readonly string $title,
        private readonly Author $author,
        private readonly array $labels,
        private readonly bool $closed,
        private readonly UriInterface $url,
    ) {
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
            'number' => Type\positive_int(),
            'title' => Type\non_empty_string(),
            'author' => Type\dict(Type\string(), Type\mixed()),
            'labels' => Type\shape([
                'nodes' => Type\vec(Type\dict(Type\string(), Type\mixed())),
            ]),
            'url' => Type\non_empty_string(),
            'closed' => Type\bool(),
            'merged' => Type\optional(Type\bool()),
        ])->coerce($payload);

        return new self(
            $payload['number'],
            $payload['title'],
            Author::fromPayload($payload['author']),
            Vec\map($payload['labels']['nodes'], Label::fromPayload(...)),
            isset($payload['merged']) ? $payload['merged'] || $payload['closed'] : $payload['closed'],
            new Uri($payload['url']),
        );
    }

    public function number(): int
    {
        return $this->number;
    }

    /** @psalm-return non-empty-string */
    public function title(): string
    {
        return $this->title;
    }

    public function author(): Author
    {
        return $this->author;
    }

    /**
     * @return array<int, Label>
     * @psalm-return list<Label>
     */
    public function labels(): array
    {
        return $this->labels;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    public function url(): UriInterface
    {
        return $this->url;
    }
}
