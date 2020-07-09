<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Webmozart\Assert\Assert;

use function Safe\json_decode;

/** @psalm-immutable */
final class MilestoneClosedEvent
{
    private SemVerVersion $version;
    private RepositoryName $repository;
    private int $milestoneNumber;

    private function __construct(
        SemVerVersion $version,
        RepositoryName $repository,
        int $milestoneNumber
    ) {
        $this->version         = $version;
        $this->repository      = $repository;
        $this->milestoneNumber = $milestoneNumber;
    }

    /** @psalm-suppress ImpureMethodCall {@see \Safe\json_encode()} is pure */
    public static function fromEventJson(string $json): self
    {
        $event = json_decode($json, true);

        Assert::isMap($event);
        Assert::keyExists($event, 'milestone');
        Assert::keyExists($event, 'repository');
        Assert::keyExists($event, 'action');
        Assert::same($event['action'], 'closed');
        Assert::isMap($event['milestone']);
        Assert::keyExists($event['milestone'], 'title');
        Assert::keyExists($event['milestone'], 'number');
        Assert::stringNotEmpty($event['milestone']['title']);
        Assert::integer($event['milestone']['number']);
        Assert::greaterThan($event['milestone']['number'], 0);
        Assert::isMap($event['repository']);
        Assert::keyExists($event['repository'], 'full_name');
        Assert::stringNotEmpty($event['repository']['full_name']);

        return new self(
            SemVerVersion::fromMilestoneName($event['milestone']['title']),
            RepositoryName::fromFullName($event['repository']['full_name']),
            $event['milestone']['number']
        );
    }

    public function repository(): RepositoryName
    {
        return $this->repository;
    }

    public function milestoneNumber(): int
    {
        return $this->milestoneNumber;
    }

    public function version(): SemVerVersion
    {
        return $this->version;
    }
}
