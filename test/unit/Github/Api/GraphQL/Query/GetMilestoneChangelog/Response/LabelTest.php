<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Label;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psl\Type\Exception\CoercionException;

final class LabelTest extends TestCase
{
    public function test(): void
    {
        $label = Label::fromPayload([
            'name'  => 'BC Break',
            'color' => 'abcabc',
            'url'   => 'https://example.com/',
        ]);

        self::assertSame('BC Break', $label->name());
        self::assertSame('abcabc', $label->colour());
        self::assertSame('https://example.com/', $label->url()->__toString());
    }

    public function testMalformedLabel(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Malformed label color.');

        Label::fromPayload([
            'name'  => 'BC Break',
            'color' => 'hello-world',
            'url'   => 'https://example.com/',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @dataProvider provideInvalidPayload
     */
    public function testInvalidPayload(array $payload): void
    {
        $this->expectException(CoercionException::class);

        Label::fromPayload($payload);
    }

    /**
     * @return iterable<int, list<array<string, mixed>>>
     */
    public function provideInvalidPayload(): iterable
    {
        yield [
            [
                'name'  => ['a', 'b'],
                'color' => 'ffffff',
                'url'   => 'https://example.com/',
            ],
        ];

        yield [
            [
                'name'  => '',
                'color' => 'ffffff',
                'url'   => 'https://example.com/',
            ],
        ];

        yield [
            [
                'name'  => 'BC Break',
                'color' => '',
                'url'   => 'https://example.com/',
            ],
        ];

        yield [
            [
                'name'  => 'BC Break',
                'color' => 'ffffff',
                'url'   => '',
            ],
        ];

        yield [
            [
                'name'  => 'BC Break',
                'color' => 'ffffff',
            ],
        ];

        yield [
            ['name' => 'BC Break'],
        ];

        yield [
            ['username' => 'BC Break'],
        ];
    }
}
