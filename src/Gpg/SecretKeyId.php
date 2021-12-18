<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use function Psl\invariant;
use function Psl\Regex\matches;
use function Psl\Str\is_empty;

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
    public static function fromBase16String(string $secretKeyId): self
    {
        invariant(! is_empty($secretKeyId), 'Expected a non-empty key id.');
        invariant(matches($secretKeyId, '/^[A-F0-9]+$/i'), 'Key id is malformed.');

        return new self($secretKeyId);
    }

    /** @psalm-return non-empty-string */
    public function __toString(): string
    {
        return $this->id;
    }
}
