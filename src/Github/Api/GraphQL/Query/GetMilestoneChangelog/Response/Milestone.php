<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;

use function array_map;
use function array_merge;
use function Safe\array_combine;

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
     *
     * @psalm-param non-empty-string $title
     * @psalm-param list<IssueOrPullRequest> $entries
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
     * @psalm-suppress ImpureMethodCall the {@see Uri} constructor is pure
     */
    public static function fromPayload(array $payload): self
    {
        Assert::keyExists($payload, 'number');
        Assert::keyExists($payload, 'closed');
        Assert::keyExists($payload, 'title');
        Assert::keyExists($payload, 'description');
        Assert::keyExists($payload, 'issues');
        Assert::keyExists($payload, 'pullRequests');
        Assert::keyExists($payload, 'url');

        Assert::integer($payload['number']);
        Assert::greaterThan($payload['number'], 0);
        Assert::boolean($payload['closed']);
        Assert::stringNotEmpty($payload['title']);
        Assert::nullOrString($payload['description']);
        Assert::stringNotEmpty($payload['url']);

        Assert::isMap($payload['issues']);
        Assert::keyExists($payload['issues'], 'nodes');

        Assert::isMap($payload['pullRequests']);
        Assert::keyExists($payload['pullRequests'], 'nodes');

        $issues       = $payload['issues']['nodes'];
        $pullRequests = $payload['pullRequests']['nodes'];

        Assert::isList($issues);
        Assert::isList($pullRequests);
        Assert::allIsMap($issues);
        Assert::allIsMap($pullRequests);

        return new self(
            $payload['number'],
            $payload['closed'],
            $payload['title'],
            $payload['description'],
            array_merge(
                array_map([IssueOrPullRequest::class, 'fromPayload'], $issues),
                array_map([IssueOrPullRequest::class, 'fromPayload'], $pullRequests)
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
     * @psalm-suppress ImpureMethodCall the {@see UriInterface} API is pure by design
     * @psalm-suppress ImpureFunctionCall the {@see \Safe\array_combine()} API is pure by design
     */
    public function assertAllIssuesAreClosed(): void
    {
        Assert::allTrue(array_combine(
            array_map(static function (IssueOrPullRequest $entry): string {
                return $entry->url()
                    ->__toString();
            }, $this->entries),
            array_map(static function (IssueOrPullRequest $entry): bool {
                return $entry->closed();
            }, $this->entries)
        ));
    }
}
