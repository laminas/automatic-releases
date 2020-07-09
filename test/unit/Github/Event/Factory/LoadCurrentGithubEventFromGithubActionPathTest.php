<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Event\Factory;

use Doctrine\AutomaticReleases\Environment\EnvironmentVariables;
use Doctrine\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use PHPUnit\Framework\TestCase;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

/** @covers \Doctrine\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath */
final class LoadCurrentGithubEventFromGithubActionPathTest extends TestCase
{
    public function testWillLoadEventFile(): void
    {
        $variables = $this->createMock(EnvironmentVariables::class);

        $eventData = <<<'JSON'
{
    "milestone": {
        "title": "1.2.3",
        "number": 123
    },
    "repository": {
        "full_name": "foo/bar"
    },
    "action": "closed"
}
JSON;
        $event = tempnam(sys_get_temp_dir(), 'github_event');

        file_put_contents($event, $eventData);

        $variables->method('githubEventPath')
            ->willReturn($event);

        self::assertEquals(
            MilestoneClosedEvent::fromEventJson($eventData),
            (new LoadCurrentGithubEventFromGithubActionPath($variables))
                ->__invoke()
        );
    }
}
