<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Value;

use Laminas\Diactoros\Uri;
use Psl;
use Psl\Regex;
use Psl\Str;
use Psl\Type;
use Psr\Http\Message\UriInterface;

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

    /**
     * @psalm-pure
     * @psalm-suppress ImpureFunctionCall the {@see \Psl\Type\non_empty_string()} API is pure by design
     * @psalm-suppress ImpureMethodCall the {@see \Psl\Type\TypeInterface::assert()} API is conditionally pure
     */
    public static function fromFullName(string $fullName): self
    {
        Psl\invariant(Regex\matches($fullName, '~^[a-zA-Z0-9_\\.-]+/[a-zA-Z0-9_\\.-]+$~'), 'Invalid repository name.');

        [$owner, $name] = Str\split($fullName, '/');

        return new self(
            Type\non_empty_string()->assert($owner),
            Type\non_empty_string()->assert($name),
        );
    }

    public function assertMatchesOwner(string $owner): void
    {
        Psl\invariant(Str\lowercase($owner) === Str\lowercase($this->owner), 'Failed asserting that "%s" matches repository name owner.', $owner);
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
