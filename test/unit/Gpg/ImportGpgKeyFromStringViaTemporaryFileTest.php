<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg;

use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psl\Shell\Exception\FailedExecutionException;

use function Psl\File\read;

/** @covers \Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile */
final class ImportGpgKeyFromStringViaTemporaryFileTest extends TestCase
{
    public function testWillImportValidGpgKey(): void
    {
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            (new ImportGpgKeyFromStringViaTemporaryFile())
                ->__invoke(read(__DIR__ . '/../../asset/dummy-gpg-key.asc')),
        );
    }

    public function testWillImportGpgKeyWithValidSubkey(): void
    {
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            (new ImportGpgKeyFromStringViaTemporaryFile())
                ->__invoke(read(__DIR__ . '/../../asset/dummy-gpg-only-subkey.asc')),
        );
    }

    public function testWillFailOnNoSecretKey(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Imported GPG key material does not contain secret key');
        (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(read(__DIR__ . '/../../asset/dummy-gpg-key-no-secret.asc'));
    }

    public function testWillFailOnInvalidGpgKey(): void
    {
        $this->expectException(FailedExecutionException::class);
        (new ImportGpgKeyFromStringViaTemporaryFile())->__invoke('-----BEGIN PGP PRIVATE KEY BLOCK-----');
    }
}
