<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Gpg;

use Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use function file_get_contents;

/** @covers \Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile */
final class ImportGpgKeyFromStringViaTemporaryFileTest extends TestCase
{
    public function testWillImportValidGpgKey(): void
    {
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            (new ImportGpgKeyFromStringViaTemporaryFile())
                ->__invoke(file_get_contents(__DIR__ . '/../../asset/dummy-gpg-key.asc'))
        );
    }
}
