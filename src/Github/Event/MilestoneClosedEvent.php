<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Event;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psl\Json;
use Psl\Type;

/** @psalm-immutable */
final class MilestoneClosedEvent
{
    private function __construct(
        private readonly SemVerVersion $version,
        private readonly RepositoryName $repository,
        private readonly int $milestoneNumber,
    ) {
    }

    public static function fromEventJson(string $json): self
    {
        $event = Json\typed($json, Type\shape([
            'milestone' => Type\shape([
                'title' => Type\non_empty_string(),
                'number' => Type\positive_int(),
            ]),
            'repository' => Type\shape([
                'full_name' => Type\non_empty_string(),
            ]),
            'action' => Type\literal_scalar('closed'),
        ]));

        return new self(
            SemVerVersion::fromMilestoneName($event['milestone']['title']),
            RepositoryName::fromFullName($event['repository']['full_name']),
            $event['milestone']['number'],
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
