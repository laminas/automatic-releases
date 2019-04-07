<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Author;
use PHPUnit\Framework\TestCase;

final class AuthorTest extends TestCase
{
    public function test() : void
    {
        $author = Author::make([
            'login' => 'Magoo',
            'url'   => 'http://example.com/',
        ]);

        self::assertSame('Magoo', $author->name());
        self::assertSame('http://example.com/', $author->url()->__toString());
    }
}
