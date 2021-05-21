<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg;

use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Filesystem;

/** @covers \Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile */
final class ImportGpgKeyFromStringViaTemporaryFileTest extends TestCase
{
    public function testWillImportValidGpgKey(): void
    {
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            (new ImportGpgKeyFromStringViaTemporaryFile())
                ->__invoke(Filesystem\read_file(__DIR__ . '/../../asset/dummy-gpg-key.asc'))
        );
    }
}
