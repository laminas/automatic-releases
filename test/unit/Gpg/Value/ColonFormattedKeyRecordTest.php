<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg\Value;

use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Laminas\AutomaticReleases\Gpg\Value\ColonFormattedKeyRecord;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

/** @covers \Laminas\AutomaticReleases\Gpg\Value\ColonFormattedKeyRecord */
class ColonFormattedKeyRecordTest extends TestCase
{
    /** @return array<string, array{string, bool, bool}> */
    public static function keyRecordLineProvider(): array
    {
        return [
            'primary key' => ['pub:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true, false],
            'primary secret key' => ['sec:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true, true],
            'subkey' => ['sub:-:2048:1:8CA5C026AE941316:1594306790:::-:::esca::::::23::0:', false, false],
            'secret subkey' => ['ssb:-:2048:1:8CA5C026AE941316:1594306790:::-:::esca::::::23::0:', false, true],
        ];
    }

    /** @dataProvider keyRecordLineProvider */
    public function testFromRecordLine(string $recordLine, bool $isPrimary, bool $isSecret): void
    {
        $record = ColonFormattedKeyRecord::fromRecordLine($recordLine);

        self::assertNotNull($record);
        self::assertSame($isPrimary, $record->isPrimaryKey());
        self::assertSame(! $isPrimary, $record->isSubkey());
        self::assertSame($isSecret, $record->isSecretKey());
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            $record->keyId(),
        );
    }

    /** @return array<string, array{string, bool}> */
    public static function recordLineCapabilitiesProvider(): array
    {
        return [
            'primary with sign' => ['pub:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true],
            'primary no sign' => ['pub:-:2048:1:8CA5C026AE941316:1594306790:::-:::ecaESCA::::::23::0:', false],
            'primary no capabilities' => ['pub:-:2048:1:8CA5C026AE941316:1594306790:::-:::::::::23::0:', false],
            'primary no capabilities field' => ['pub:-:2048:1:8CA5C026AE941316', false],
            'primary secret with sign' => ['sec:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true],
            'primary secret no sign' => ['sec:-:2048:1:8CA5C026AE941316:1594306790:::-:::ecaESCA::::::23::0:', false],
            'subkey with sign' => ['sub:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true],
            'subkey no sign' => ['sub:-:2048:1:8CA5C026AE941316:1594306790:::-:::ecaESCA::::::23::0:', false],
            'subkey no capabilities' => ['sub:-:2048:1:8CA5C026AE941316:1594306790:::-:::::::::23::0:', false],
            'secret subkey with sign' => ['ssb:-:2048:1:8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:', true],
            'secret subkey no sign' => ['ssb:-:2048:1:8CA5C026AE941316:1594306790:::-:::ecaESCA::::::23::0:', false],
        ];
    }

    /** @dataProvider recordLineCapabilitiesProvider */
    public function testFromRecordLineSignCapability(string $recordLine, bool $hasSign): void
    {
        $record = ColonFormattedKeyRecord::fromRecordLine($recordLine);

        self::assertNotNull($record);
        self::assertSame($hasSign, $record->hasSignCapability());
    }

    public function testMalformedKeyIdInvariant(): void
    {
        $this->expectException(InvariantViolationException::class);
        ColonFormattedKeyRecord::fromRecordLine('pub:-:2048:1:0X8CA5C026AE941316:1594306790:::-:::escaESCA::::::23::0:');
    }

    public function testMissingKeyIdInvariant(): void
    {
        $this->expectException(InvariantViolationException::class);
        ColonFormattedKeyRecord::fromRecordLine('pub:-:2048:1::1594306790:::-:::escaESCA::::::23::0:');
    }

    /** @return array<string, array{string}> */
    public static function unrelatedRecordLineProvider(): array
    {
        return [
            'fingerprint' => ['fpr:::::::::3F548E613B430AAA0040513E8CA5C026AE941316:'],
            'keygrip' => ['grp:::::::::6541B11573E0968A3C6F831350B04B6336DE6BDF:'],
            'empty' => [''],
            'empty with delimiters' => ['::'],
            'unknown' => ['unknown::::::::::::::::::::'],
        ];
    }

    /** @dataProvider unrelatedRecordLineProvider */
    public function testFromRecordLineIgnoresNonKeyTypes(string $recordLine): void
    {
        $record = ColonFormattedKeyRecord::fromRecordLine($recordLine);
        self::assertNull($record);
    }
}
