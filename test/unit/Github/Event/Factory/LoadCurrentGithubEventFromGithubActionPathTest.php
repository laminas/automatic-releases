<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Event\Factory;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;

/** @covers \Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath */
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
        $event     = Filesystem\create_temporary_file(Env\temp_dir(), 'github_event');

        Filesystem\write_file($event, $eventData);

        $variables->method('githubEventPath')
            ->willReturn($event);

        self::assertEquals(
            MilestoneClosedEvent::fromEventJson($eventData),
            (new LoadCurrentGithubEventFromGithubActionPath($variables))
                ->__invoke()
        );
    }
}
