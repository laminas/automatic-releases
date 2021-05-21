<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg;

use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

final class SecretKeyIdTest extends TestCase
{
    /**
     * @dataProvider invalidKeys
     */
    public function testRejectsInvalidKeyIds(string $invalid): void
    {
        $this->expectException(InvariantViolationException::class);

        SecretKeyId::fromBase16String($invalid);
    }

    /** @return array<int, array<int, string>> */
    public function invalidKeys(): array
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
    public function testAcceptsValidKeyIds(string $valid): void
    {
        self::assertSame(
            $valid,
            SecretKeyId::fromBase16String($valid)
                       ->id()
        );
    }

    /** @return array<int, array<int, string>> */
    public function validKeys(): array
    {
        return [
            ['123'],
            ['abc'],
            ['aaaaaaaaaaaaaaaaaaaa'],
            ['AAAAAAAAAAAAAAAAAAAA'],
        ];
    }
}
