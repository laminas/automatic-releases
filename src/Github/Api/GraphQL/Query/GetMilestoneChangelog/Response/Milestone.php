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
final class Milestone
{
    private int $number;
    private bool $closed;
    /** @psalm-var non-empty-string */
    private string $title;
    private ?string $description;
    /**
     * @var array<int, IssueOrPullRequest>
     * @psalm-var list<IssueOrPullRequest>
     */
    private array $entries;
    private UriInterface $url;

    /**
     * @param array<int, IssueOrPullRequest> $entries
     * @psalm-param non-empty-string $title
     * @psalm-param list<IssueOrPullRequest> $entries
     *
     * @psalm-suppress ImpurePropertyAssignment {@see UriInterface} is pure
     */
    private function __construct(
        int $number,
        bool $closed,
        string $title,
        ?string $description,
        array $entries,
        UriInterface $url
    ) {
        $this->number      = $number;
        $this->closed      = $closed;
        $this->title       = $title;
        $this->description = $description;
        $this->entries     = $entries;
        $this->url         = $url;
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
                Vec\map($payload['issues']['nodes'], [IssueOrPullRequest::class, 'fromPayload']),
                Vec\map($payload['pullRequests']['nodes'], [IssueOrPullRequest::class, 'fromPayload'])
            ),
            new Uri($payload['url'])
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

    public function description(): ?string
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

    /**
     * @psalm-suppress ImpureFunctionCall the {@see \Psl\Iter\all()} API is conditionally pure
     */
    public function assertAllIssuesAreClosed(): void
    {
        Psl\invariant(Iter\all($this->entries, static function (IssueOrPullRequest $entry): bool {
            return $entry->closed();
        }), 'Failed asserting that all milestone issues are closed.');
    }
}
