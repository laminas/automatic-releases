<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psl;
use Psl\Iter;
use Psl\Type;
use Psl\Vec;
use Psr\Http\Message\UriInterface;

/** @psalm-immutable */
final readonly class Milestone
{
    /**
     * @param array<int, IssueOrPullRequest> $entries
     * @psalm-param non-empty-string $title
     * @psalm-param list<IssueOrPullRequest> $entries
     */
    private function __construct(
        private readonly int $number,
        private readonly bool $closed,
        private readonly string $title,
        private readonly string|null $description,
        private readonly array $entries,
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
            'closed' => Type\bool(),
            'title' => Type\non_empty_string(),
            'description' => Type\union(Type\null(), Type\string()),
            'url' => Type\non_empty_string(),
            'issues' => Type\shape([
                'nodes' => Type\vec(Type\dict(Type\string(), Type\mixed())),
            ]),
            'pullRequests' => Type\shape([
                'nodes' => Type\vec(Type\dict(Type\string(), Type\mixed())),
            ]),
        ])->coerce($payload);

        return new self(
            $payload['number'],
            $payload['closed'],
            $payload['title'],
            $payload['description'],
            Vec\concat(
                Vec\map($payload['issues']['nodes'], IssueOrPullRequest::fromPayload(...)),
                Vec\map($payload['pullRequests']['nodes'], IssueOrPullRequest::fromPayload(...)),
            ),
            new Uri($payload['url']),
        );
    }

    public function number(): int
    {
        return $this->number;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    /** @psalm-return non-empty-string */
    public function title(): string
    {
        return $this->title;
    }

    public function description(): string|null
    {
        return $this->description;
    }

    /** @return array<int, IssueOrPullRequest> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function url(): UriInterface
    {
        return $this->url;
    }

    /** @psalm-suppress ImpureFunctionCall the {@see \Psl\Iter\all()} API is conditionally pure */
    public function assertAllIssuesAreClosed(): void
    {
        Psl\invariant(Iter\all($this->entries, static fn (IssueOrPullRequest $entry): bool => $entry->closed()), 'Failed asserting that all milestone issues are closed.');
    }
}
