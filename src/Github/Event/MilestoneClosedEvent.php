<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Event;

use Assert\Assert;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Message\ServerRequestInterface;

final class MilestoneClosedEvent
{
    /** @var SemVerVersion */
    private $version;

    /** @var RepositoryName */
    private $repository;

    /** @var int */
    private $milestoneNumber;

    private function __construct()
    {
    }

    public static function appliesToRequest(ServerRequestInterface $request) : bool
    {
        if ($request->getHeaderLine('X-Github-Event') !== 'milestone') {
            return false;
        }

        $body = $request->getParsedBody();

        \assert(is_array($body));

        if (! array_key_exists('payload', $body)) {
            return false;
        }

        $event = \Safe\json_decode($body['payload'], true);

        return $event['action'] === 'closed';
    }

    public static function fromEventJson(string $json) : self
    {
        $event = \Safe\json_decode($json, true);

        Assert
            ::that($event)
            ->keyExists('milestone')
            ->keyExists('repository')
            ->keyExists('action');

        Assert
            ::that($event['action'])
            ->same('closed');

        Assert
            ::that($event['milestone'])
            ->keyExists('title')
            ->keyExists('number');

        Assert
            ::that($event['milestone']['title'])
            ->string()
            ->notEmpty();

        Assert
            ::that($event['milestone']['number'])
            ->integer()
            ->greaterThan(0);

        Assert
            ::that($event['repository'])
            ->keyExists('full_name');

        $instance = new self();

        $instance->repository      = RepositoryName::fromFullName($event['repository']['full_name']);
        $instance->milestoneNumber = $event['milestone']['number'];
        $instance->version         = SemVerVersion::fromMilestoneName($event['milestone']['title']);

        return $instance;
    }

    public function repository() : RepositoryName
    {
        return $this->repository;
    }

    public function milestoneNumber() : int
    {
        return $this->milestoneNumber;
    }

    public function version() : SemVerVersion
    {
        return $this->version;
    }
}
