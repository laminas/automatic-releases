<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use Psl;
use Psl\Regex;
use Psl\Str;

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
    public static function fromBase16String(string $keyId): self
    {
        Psl\invariant(! Str\is_empty($keyId), 'Expected a non-empty key id.');
        Psl\invariant(Regex\matches($keyId, '/^[A-F0-9]+$/i'), 'Key id is malformed.');

        return new self($keyId);
    }

    /** @psalm-return non-empty-string */
    public function id(): string
    {
        return $this->id;
    }
}
