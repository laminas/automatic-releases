<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git\Value;

use Psl;
use Psl\Regex;
use Psl\Type;

/** @psalm-immutable */
final class BranchName
{
    /** @psalm-var non-empty-string */
    private string $name;

    /** @psalm-param non-empty-string $name */
    private function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @pure
     * @psalm-suppress ImpureFunctionCall the {@see \Psl\Type\non_empty_string()} API is pure by design
     * @psalm-suppress ImpureMethodCall the {@see \Psl\Type\TypeInterface::assert()} API is conditionally pure
     */
    public static function fromName(string $name): self
    {
        return new self(Type\non_empty_string()->assert($name));
    }

    /** @psalm-return non-empty-string */
    public function name(): string
    {
        return $this->name;
    }

    public function isReleaseBranch(): bool
    {
        return Regex\matches($this->name, '/^(v)?\d+\\.\d+(\\.x)?$/');
    }

    /**
     * @return array<int, int>
     * @psalm-return array{0: int, 1: int}
     *
     * @psalm-suppress ImpureFunctionCall the {@see \Psl\Type\int()} and {@see \Psl\Type\shape()} APIs are pure by design
     * @psalm-suppress ImpureMethodCall the {@see \Psl\Type\TypeInterface::assert()} API is conditionally pure
     */
    public function majorAndMinor(): array
    {
        $match = Regex\first_match($this->name, '/^(?:v)?(\d+)\\.(\d+)(?:\\.x)?$/', Regex\capture_groups([0, 1, 2]));

        Psl\invariant($match !== null, 'Invalid branch name.');

        [, $major, $minor] = $match;

        return Type\shape([Type\int(), Type\int()])->coerce([$major, $minor]);
    }

    public function targetMinorReleaseVersion(): SemVerVersion
    {
        [$major, $minor] = $this->majorAndMinor();

        return SemVerVersion::fromMilestoneName($major . '.' . $minor . '.0');
    }

    public function equals(self $other): bool
    {
        return $other->name === $this->name;
    }

    public function isForVersion(SemVerVersion $version): bool
    {
        return $this->majorAndMinor() === [$version->major(), $version->minor()];
    }

    public function isForNewerVersionThan(SemVerVersion $version): bool
    {
        [$major, $minor]      = $this->majorAndMinor();
        $comparedMajorVersion = $version->major();

        return $major > $comparedMajorVersion
            || ($comparedMajorVersion === $major && $minor > $version->minor());
    }
}
