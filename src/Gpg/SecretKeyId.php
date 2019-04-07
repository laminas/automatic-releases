<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Gpg;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

final class SecretKeyId
{
    /** @var string */
    private $id;

    private function __construct()
    {
    }

    public static function fromBase16String(string $keyId) : self
    {
        Assert::that($keyId)
            ->regex('/^[A-F0-9]+$/i');

        $instance = new self();

        $instance->id = $keyId;

        return $instance;
    }

    public function id() : string
    {
        return $this->id;
    }
}
