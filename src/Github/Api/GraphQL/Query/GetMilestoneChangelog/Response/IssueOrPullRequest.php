<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;
use Zend\Diactoros\Uri;
use function array_map;
use function array_values;

/** @psalm-immutable */
final class IssueOrPullRequest
{
    private int $number;
    /** @psalm-var non-empty-string */
    private string $title;
    private Author $author;
    /**
     * @var array<int, Label>
     *
     * @psalm-var list<Label>
     */
    private array $labels;
    private bool $closed;
    private UriInterface $url;

    /**
     * @psalm-param non-empty-string $title
     * @psalm-param list<Label> $labels
     *
     * @psalm-suppress ImpurePropertyAssignment {@see UriInterface} is pure
     */
    private function __construct(
        int $number,
        string $title,
        Author $author,
        array $labels,
        bool $closed,
        UriInterface $url
    ) {
        $this->number = $number;
        $this->title  = $title;
        $this->author = $author;
        $this->labels = $labels;
        $this->closed = $closed;
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
        Assert::keyExists($payload, 'number');
        Assert::keyExists($payload, 'title');
        Assert::keyExists($payload, 'author');
        Assert::keyExists($payload, 'url');
        Assert::keyExists($payload, 'closed');
        Assert::keyExists($payload, 'labels');
        Assert::integer($payload['number']);
        Assert::greaterThan($payload['number'], 0);
        Assert::stringNotEmpty($payload['title']);
        Assert::isMap($payload['author']);
        Assert::isMap($payload['labels']);
        Assert::keyExists($payload['labels'], 'nodes');
        Assert::isList($payload['labels']['nodes']);
        Assert::stringNotEmpty($payload['url']);
        Assert::boolean($payload['closed']);

        return new self(
            $payload['number'],
            $payload['title'],
            Author::fromPayload($payload['author']),
            array_values(array_map([Label::class, 'fromPayload'], $payload['labels']['nodes'])),
            isset($payload['merged'])
                ? (bool) $payload['merged'] || $payload['closed']
                : $payload['closed'],
            new Uri($payload['url'])
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
     *
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
