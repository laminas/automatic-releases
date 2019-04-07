<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Value;

use Assert\AssertionFailedException;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;

final class RepositoryNameTest extends TestCase
{
    public function test()
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

        $this->expectException(AssertionFailedException::class);

        $repositoryName->assertMatchesOwner('potato');
    }
}
