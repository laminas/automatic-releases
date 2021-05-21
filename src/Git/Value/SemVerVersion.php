<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git\Value;

use Psl;
use Psl\Regex;

/** @psalm-immutable */
final class SemVerVersion
{
    private int $major;
    private int $minor;
    private int $patch;

    private function __construct(int $major, int $minor, int $patch)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
    }

    /**
     * @psalm-pure
     * @psalm-suppress ImpureFunctionCall {@see https://github.com/azjezz/psl/issues/130}
     */
    public static function fromMilestoneName(string $name): self
    {
        Psl\invariant(Regex\matches($name, '/^(v)?\\d+\\.\\d+\\.\\d+$/'), 'Milestone name is malformed.');

        $matches = Regex\first_match($name, '/(\\d+)\\.(\\d+)\\.(\\d+)/', Regex\capture_groups([1, 2, 3]));

        Psl\invariant($matches !== null, 'Expected milestone name to match.');

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /** @psalm-return non-empty-string */
    public function fullReleaseName(): string
    {
        return $this->major . '.' . $this->minor . '.' . $this->patch;
    }

    public function major(): int
    {
        return $this->major;
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function nextPatch(): self
    {
        return new self($this->major, $this->minor, $this->patch + 1);
    }

    public function nextMinor(): self
    {
        return new self($this->major, $this->minor + 1, 0);
    }

    public function nextMajor(): self
    {
        return new self($this->major + 1, 0, 0);
    }

    public function targetReleaseBranchName(): BranchName
    {
        return BranchName::fromName($this->major . '.' . $this->minor . '.x');
    }

    public function isNewMinorRelease(): bool
    {
        return $this->patch === 0;
    }

    public function isNewMajorRelease(): bool
    {
        return $this->minor === 0 && $this->patch === 0;
    }

    public function lessThanEqual(self $other): bool
    {
        return $this->compare($other) <= 0;
    }

    private function compare(self $other): int
    {
        return [$this->major, $this->minor, $this->patch] <=> [$other->major, $other->minor, $other->patch];
    }
}
