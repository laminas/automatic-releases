<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Application;

use Laminas\AutomaticReleases\Application\Command\CreateMilestones;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneFailed;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Laminas\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @covers \Laminas\AutomaticReleases\Application\Command\CreateMilestones
 */
final class CreateMilestonesTest extends TestCase
{
    /** @var LoadCurrentGithubEvent&MockObject */
    private LoadCurrentGithubEvent $loadEvent;

    /** @var CreateMilestone&MockObject */
    private CreateMilestone $createMilestone;

    private CreateMilestones $command;

    private MilestoneClosedEvent $event;

    private SemVerVersion $releaseVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadEvent       = $this->createMock(LoadCurrentGithubEvent::class);
        $this->createMilestone = $this->createMock(CreateMilestone::class);

        $this->command = new CreateMilestones(
            $this->loadEvent,
            $this->createMilestone
        );

        $this->event = MilestoneClosedEvent::fromEventJson(<<<'JSON'
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
        JSON
        );

        $this->releaseVersion = SemVerVersion::fromMilestoneName('1.2.3');
    }

    public function testCommandName(): void
    {
        self::assertSame('laminas:automatic-releases:create-milestones', $this->command->getName());
    }

    public function testWillCreate(): void
    {
        $this->loadEvent
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn($this->event);

        $this->createMilestone
            ->expects(self::exactly(3))
            ->method('__invoke')
            ->withConsecutive(
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextPatch()),
                ],
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextMinor()),
                ],
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextMajor()),
                ],
            );

        self::assertSame(0, $this->command->execute(new ArrayInput([]), new NullOutput()));
    }

    public function testWillFailed(): void
    {
        $this->loadEvent
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn($this->event);

        $this->createMilestone
            ->expects(self::exactly(3))
            ->method('__invoke')
            ->withConsecutive(
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextPatch()),
                ],
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextMinor()),
                ],
                [
                    self::equalTo(RepositoryName::fromFullName('foo/bar')),
                    self::equalTo($this->releaseVersion->nextMajor()),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                self::throwException(
                    CreateMilestoneFailed::forVersion($this->releaseVersion->nextPatch()->fullReleaseName()),
                ),
                self::throwException(
                    CreateMilestoneFailed::forVersion($this->releaseVersion->nextMinor()->fullReleaseName()),
                ),
                self::throwException(
                    CreateMilestoneFailed::forVersion($this->releaseVersion->nextMajor()->fullReleaseName()),
                ),
            );

        self::assertSame(0, $this->command->execute(new ArrayInput([]), new NullOutput()));
    }
}
