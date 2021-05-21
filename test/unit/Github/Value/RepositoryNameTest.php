<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Value;

use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

final class RepositoryNameTest extends TestCase
{
    public function test(): void
    {
        $repositoryName = RepositoryName::fromFullName('foo/bar');

        self::assertSame('foo', $repositoryName->owner());
        self::assertSame('bar', $repositoryName->name());
        self::assertSame(
            'https://token:x-oauth-basic@github.com/foo/bar.git',
            $repositoryName
                ->uriWithTokenAuthentication('token')
                ->__toString()
        );

        /** @psalm-suppress UnusedMethodCall */
        $repositoryName->assertMatchesOwner('foo');

        $this->expectException(InvariantViolationException::class);

        /** @psalm-suppress UnusedMethodCall */
        $repositoryName->assertMatchesOwner('potato');
    }
}
