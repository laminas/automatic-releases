<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;

/** @psalm-immutable */
final class ReleaseChangelogEvent
{
    public Milestone $milestone;

    /** @psalm-var non-empty-string */
    public string $repositoryDirectory;

    public RepositoryName $repositoryName;
    public BranchName $sourceBranch;
    public SemVerVersion $version;

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __construct(
        RepositoryName $repositoryName,
        string $repositoryDirectory,
        BranchName $sourceBranch,
        Milestone $milestone,
        SemVerVersion $version
    ) {
        $this->repositoryName      = $repositoryName;
        $this->repositoryDirectory = $repositoryDirectory;
        $this->sourceBranch        = $sourceBranch;
        $this->milestone           = $milestone;
        $this->version             = $version;
    }
}
