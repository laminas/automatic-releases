<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Webmozart\Assert\Assert;
use function array_map;
use function assert;
use function is_array;
use function Safe\preg_match;

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

    /** @psalm-pure */
    public static function fromName(string $name) : self
    {
        Assert::stringNotEmpty($name);

        return new self($name);
    }

    /** @psalm-return non-empty-string */
    public function name() : string
    {
        return $this->name;
    }

    /**
     * @psalm-suppress ImpureFunctionCall the {@see \Safe\preg_match()} API is pure by design
     */
    public function isReleaseBranch() : bool
    {
        return preg_match('/^(v)?\d+\\.\d+(\\.x)?$/', $this->name) === 1;
    }

    public function isNextMajor() : bool
    {
        return $this->name === 'master';
    }

    /**
     * @return array<int, int>
     *
     * @psalm-return array{0: int, 1: int}
     *
     * @psalm-suppress ImpureFunctionCall the {@see \Safe\preg_match()} API is pure by design
     */
    public function majorAndMinor() : array
    {
        Assert::regex($this->name, '/^(v)?\d+\\.\d+(\\.x)?$/');

        preg_match('/^(?:v)?(\d+)\\.(\d+)(?:\\.x)?$/', $this->name, $matches);

        assert(is_array($matches));

        [, $major, $minor] = array_map('intval', $matches);

        return [$major, $minor];
    }

    public function equals(self $other) : bool
    {
        return $other->name === $this->name;
    }

    public function isForVersion(SemVerVersion $version) : bool
    {
        return $this->majorAndMinor() === [$version->major(), $version->minor()];
    }

    public function isForNewerVersionThan(SemVerVersion $version) : bool
    {
        [$major, $minor]      = $this->majorAndMinor();
        $comparedMajorVersion = $version->major();

        return $major > $comparedMajorVersion
            || ($comparedMajorVersion === $major && $minor > $version->minor());
    }
}
