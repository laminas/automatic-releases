<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;
use function array_map;
use function assert;
use function is_array;
use function Safe\preg_match;

final class SemVerVersion
{
    /** @var int */
    private $major;

    /** @var int */
    private $minor;

    /** @var int */
    private $patch;

    private function __construct()
    {
    }

    public static function fromMilestoneName(string $name) : self
    {
        Assert::that($name)
            ->notEmpty()
            ->regex('/^(v)?\\d+\\.\\d+\\.\\d+$/');

        preg_match('/(\\d+)\\.(\\d+)\\.(\\d+)/', $name, $matches);

        assert(is_array($matches));

        $instance = new self();

        [, $instance->major, $instance->minor, $instance->patch] = array_map('intval', $matches);

        return $instance;
    }

    public function fullReleaseName() : string
    {
        return $this->major . '.' . $this->minor . '.' . $this->patch;
    }

    public function major() : int
    {
        return $this->major;
    }

    public function minor() : int
    {
        return $this->minor;
    }

    public function targetReleaseBranchName() : BranchName
    {
        return BranchName::fromName($this->major . '.' . $this->minor . '.x');
    }

    public function isNewMinorRelease() : bool
    {
        return $this->patch === 0;
    }
}
