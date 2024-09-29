<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg\Value;

use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Str;

use function in_array;
use function str_contains;

final readonly class ColonFormattedKeyRecord
{
    private const FIELD_TYPE         = 0;
    private const FIELD_KEYID        = 4;
    private const FIELD_CAPABILITIES = 11;

    private function __construct(
        private bool $isSubkey,
        private bool $isSecretKey,
        private SecretKeyId $keyId,
        private string $capabilities,
    ) {
    }

    public static function fromRecordLine(string $recordLine): self|null
    {
        $record = Str\split($recordLine, ':');
        $type   = $record[self::FIELD_TYPE] ?? '';
        if (! in_array($type, ['pub', 'sec', 'sub', 'ssb'])) {
            return null;
        }

        $isSubkey     = in_array($type, ['sub', 'ssb']);
        $isSecretKey  = in_array($type, ['sec', 'ssb']);
        $keyId        = SecretKeyId::fromBase16String($record[self::FIELD_KEYID] ?? '');
        $capabilities = $record[self::FIELD_CAPABILITIES] ?? '';

        return new self($isSubkey, $isSecretKey, $keyId, $capabilities);
    }

    public function isPrimaryKey(): bool
    {
        return ! $this->isSubkey;
    }

    public function isSubkey(): bool
    {
        return $this->isSubkey;
    }

    public function isSecretKey(): bool
    {
        return $this->isSecretKey;
    }

    public function keyId(): SecretKeyId
    {
        return $this->keyId;
    }

    public function hasSignCapability(): bool
    {
        return str_contains($this->capabilities, 's');
    }
}
