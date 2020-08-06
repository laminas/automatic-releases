<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_map;
use function implode;

final class ConcatenateMultipleReleaseTexts implements CreateReleaseText
{
    private const CONCATENATION_STRING = "\n\n-----\n\n";

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
    ): string {
        $items = array_map(
            static fn (CreateReleaseText $generator): string => $generator($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory),
            array_filter(
                $this->releaseTextGenerators,
                static fn (CreateReleaseText $generator): bool => $generator->canCreateReleaseText($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory)
            )
        );

        $changelog = implode(self::CONCATENATION_STRING, $items);
        Assert::notEmpty($changelog);

        return $changelog;
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
