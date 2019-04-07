<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;

final class BranchName
{
    /** @var string */
    private $name;

    private function __construct()
    {
    }

    public static function fromName(string $name) : self
    {
        Assert
            ::that($name)
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
        return \Safe\preg_match('/^(v)?\d+\\.\d+(\\.x)?$/', $this->name) === 1;
    }

    public function isNextMajor() : bool
    {
        return $this->name === 'master';
    }

    /** @return array<int, int> */
    public function majorAndMinor() : array
    {
        Assert::that($this->name)
            ->regex('/^(v)?\d+\\.\d+(\\.x)?$/');

        \Safe\preg_match('/^(?:v)?(\d+)\\.(\d+)(?:\\.x)?$/', $this->name, $matches);

        \assert(is_array($matches));

        list(, $major, $minor) = array_map('intval', $matches);

        return [$major, $minor];
    }
}
