<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use Psl;
use Psl\Regex;
use Psl\Str;

/** @psalm-immutable */
final readonly class SecretKeyId
{
    /** @psalm-param non-empty-string $id */
    private function __construct(
        /** @psalm-var non-empty-string */
        private readonly string $id,
    ) {
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
