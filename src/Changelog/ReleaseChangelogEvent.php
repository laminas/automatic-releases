<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReleaseChangelogEvent
{
    public string $author;
    public InputInterface $input;
    public Milestone $milestone;
    public OutputInterface $output;

    /** @psalm-var non-empty-string */
    public string $repositoryDirectory;

    public RepositoryName $repositoryName;
    public BranchName $sourceBranch;
    public SemVerVersion $version;

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        RepositoryName $repositoryName,
        string $repositoryDirectory,
        BranchName $sourceBranch,
        Milestone $milestone,
        SemVerVersion $version,
        string $author
    ) {
        $this->input               = $input;
        $this->output              = $output;
        $this->repositoryName      = $repositoryName;
        $this->repositoryDirectory = $repositoryDirectory;
        $this->sourceBranch        = $sourceBranch;
        $this->milestone           = $milestone;
        $this->version             = $version;
        $this->author              = $author;
    }
}
