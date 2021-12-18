<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Event\Factory;

use Laminas\AutomaticReleases\Environment\Contracts\Variables;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Test\Unit\TestCase;

use function Psl\Filesystem\write_file;

/** @covers \Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath */
final class LoadCurrentGithubEventFromGithubActionPathTest extends TestCase
{
    public function testWillLoadEventFile(): void
    {
        $environment = $this->createMock(Variables::class);

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
        $event     = $this->createTemporaryFile('github_event');

        write_file($event, $eventData);

        $environment->method('githubEventPath')
            ->willReturn($event);

        self::assertEquals(
            MilestoneClosedEvent::fromEventJson($eventData),
            (new LoadCurrentGithubEventFromGithubActionPath($environment))()
        );
    }
}
