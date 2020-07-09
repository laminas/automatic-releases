<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Value;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;

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

        $repositoryName->assertMatchesOwner('foo');

        $this->expectException(InvalidArgumentException::class);

        $repositoryName->assertMatchesOwner('potato');
    }
}
