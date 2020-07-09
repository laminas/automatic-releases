<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Value;

use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;
use Zend\Diactoros\Uri;

use function explode;
use function strtolower;

/** @psalm-immutable */
final class RepositoryName
{
    /** @psalm-var non-empty-string */
    private string $owner;

    /** @psalm-var non-empty-string */
    private string $name;

    /**
     * @psalm-param non-empty-string $owner
     * @psalm-param non-empty-string $name
     */
    private function __construct(string $owner, string $name)
    {
        $this->owner = $owner;
        $this->name  = $name;
    }

    /** @psalm-pure */
    public static function fromFullName(string $fullName): self
    {
        Assert::stringNotEmpty($fullName);
        Assert::regex($fullName, '~^[a-zA-Z0-9_\\.-]+/[a-zA-Z0-9_\\.-]+$~');

        [$owner, $name] = explode('/', $fullName);

        Assert::notEmpty($owner);
        Assert::notEmpty($name);

        return new self($owner, $name);
    }

    public function assertMatchesOwner(string $owner): void
    {
        Assert::same(strtolower($owner), strtolower($this->owner));
    }

    /** @psalm-param non-empty-string $token */
    public function uriWithTokenAuthentication(string $token): UriInterface
    {
        return new Uri('https://' . $token . ':x-oauth-basic@github.com/' . $this->owner . '/' . $this->name . '.git');
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function name(): string
    {
        return $this->name;
    }
}
