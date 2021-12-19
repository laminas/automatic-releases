<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Gpg;

use Generator;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
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

    /**
     * @return Generator<array-key, array<array-key, string>>
     */
    public function invalidKeys(): Generator
    {
        yield 'empty-string' => [''];
        yield 'foo' => ['foo'];
        yield 'abz' => ['abz'];
        yield 'FOO' => ['FOO'];
        yield '123z' => ['123z'];
    }

    /**
     * @dataProvider validKeys
     */
    public function testAcceptsValidKeyIds(string $valid): void
    {
        self::assertSame(
            $valid,
            (string) SecretKeyId::fromBase16String($valid)
        );
    }

    /** @return Generator<array-key, array<array-key, string>> */
    public function validKeys(): Generator
    {
        foreach (
            [
                '123',
                'abc',
                'aaaaaaaaaaaaaaaaaaaa',
                'AAAAAAAAAAAAAAAAAAAA',
            ] as $key
        ) {
            yield $key => [$key];
        }
    }
}
