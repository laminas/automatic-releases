<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Gpg;

use Assert\AssertionFailedException;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;

final class SecretKeyIdTest extends TestCase
{
    /**
     * @dataProvider invalidKeys
     */
    public function testRejectsInvalidKeyIds(string $invalid) : void
    {
        $this->expectException(AssertionFailedException::class);

        SecretKeyId::fromBase16String($invalid);
    }

    /** @return array<int, array<int, string>> */
    public function invalidKeys() : array
    {
        return [
            [''],
            ['foo'],
            ['abz'],
            ['FOO'],
            ['123z'],
        ];
    }

    /**
     * @dataProvider validKeys
     */
    public function testAcceptsValidKeyIds(string $valid) : void
    {
        self::assertSame(
            $valid,
            SecretKeyId::fromBase16String($valid)
                       ->id()
        );
    }

    /** @return array<int, array<int, string>> */
    public function validKeys() : array
    {
        return [
            ['123'],
            ['abc'],
            ['aaaaaaaaaaaaaaaaaaaa'],
            ['AAAAAAAAAAAAAAAAAAAA'],
        ];
    }
}
