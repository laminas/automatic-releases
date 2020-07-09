<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\Diactoros\Uri;
use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;

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
        Assert::isMap($payload);
        Assert::keyExists($payload, 'login');
        Assert::keyExists($payload, 'url');
        Assert::stringNotEmpty($payload['login']);
        Assert::stringNotEmpty($payload['url']);

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
