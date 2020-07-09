<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Gpg;

use Webmozart\Assert\Assert;

/** @psalm-immutable */
final class SecretKeyId
{
    /** @psalm-var non-empty-string */
    private string $id;

    /** @psalm-param non-empty-string $id */
    private function __construct(string $id)
    {
        $this->id = $id;
    }

    /** @psalm-pure */
    public static function fromBase16String(string $keyId) : self
    {
        Assert::notEmpty($keyId);
        Assert::regex($keyId, '/^[A-F0-9]+$/i');

        return new self($keyId);
    }

    /** @psalm-return non-empty-string */
    public function id() : string
    {
        return $this->id;
    }
}
