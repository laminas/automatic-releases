<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Changelog\ChangelogReleaseNotes;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_map;
use function array_reduce;

final class MergeMultipleReleaseNotes implements CreateReleaseText
{
    /** @psalm-var non-empty-list<CreateReleaseText> */
    private array $releaseTextGenerators;

    /** @psalm-param non-empty-list<CreateReleaseText> $releaseTextGenerators */
    public function __construct(array $releaseTextGenerators)
    {
        $this->releaseTextGenerators = $releaseTextGenerators;
    }

    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): ChangelogReleaseNotes {
        $items = array_map(
            static fn (CreateReleaseText $generator): ChangelogReleaseNotes => $generator($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory),
            array_filter(
                $this->releaseTextGenerators,
                static fn (CreateReleaseText $generator): bool => $generator->canCreateReleaseText($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory)
            )
        );

        $releaseNotes = array_reduce(
            $items,
            static fn (?ChangelogReleaseNotes $releaseNotes, ChangelogReleaseNotes $item): ChangelogReleaseNotes => $releaseNotes ? $releaseNotes->merge($item) : $item
        );

        Assert::isInstanceOf($releaseNotes, ChangelogReleaseNotes::class);
        Assert::notEmpty($releaseNotes->contents());

        return $releaseNotes;
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        foreach ($this->releaseTextGenerators as $generator) {
            if (
                $generator->canCreateReleaseText(
                    $milestone,
                    $repositoryName,
                    $semVerVersion,
                    $sourceBranch,
                    $repositoryDirectory
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
