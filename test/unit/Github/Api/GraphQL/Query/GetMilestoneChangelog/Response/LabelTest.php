<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Label;
use PHPUnit\Framework\TestCase;

final class LabelTest extends TestCase
{
    public function test()
    {
        $label = Label::make([
            'name'  => 'BC Break',
            'color' => 'abcabc',
            'url'   => 'http://example.com/',
        ]);

        self::assertSame('BC Break', $label->name());
        self::assertSame('abcabc', $label->colour());
        self::assertSame('http://example.com/', $label->url()->__toString());
    }
}
