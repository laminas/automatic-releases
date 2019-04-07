<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;
use function array_map;
use function assert;
use function is_array;
use function Safe\preg_match;

final class BranchName
{
    /** @var string */
    private $name;

    private function __construct()
    {
    }

    public static function fromName(string $name) : self
    {
        Assert::that($name)
            ->notEmpty();

        $instance = new self();

        $instance->name = $name;

        return $instance;
    }

    public function name() : string
    {
        return $this->name;
    }

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
     */
    public function majorAndMinor() : array
    {
        Assert::that($this->name)
            ->regex('/^(v)?\d+\\.\d+(\\.x)?$/');

        preg_match('/^(?:v)?(\d+)\\.(\d+)(?:\\.x)?$/', $this->name, $matches);

        assert(is_array($matches));

        [, $major, $minor] = array_map('intval', $matches);

        return [$major, $minor];
    }
}
