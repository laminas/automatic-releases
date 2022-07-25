<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg;

use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;

use function Psl\File\read;

/** @covers \Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile */
final class ImportGpgKeyFromStringViaTemporaryFileTest extends TestCase
{
    public function testWillImportValidGpgKey(): void
    {
        self::assertEquals(
            SecretKeyId::fromBase16String('8CA5C026AE941316'),
            (new ImportGpgKeyFromStringViaTemporaryFile())
                ->__invoke(read(__DIR__ . '/../../asset/dummy-gpg-key.asc'))
        );
    }
}
