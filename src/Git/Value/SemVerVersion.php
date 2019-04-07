<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;

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
        Assert
            ::that($name)
            ->notEmpty()
            ->regex('/^(v)?\\d+\\.\\d+\\.\\d+$/');

        \Safe\preg_match('/(\\d+)\\.(\\d+)\\.(\\d+)/', $name, $matches);

        \assert(is_array($matches));

        $instance = new self();

        list(, $instance->major, $instance->minor, $instance->patch) = array_map('intval', $matches);

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
}
