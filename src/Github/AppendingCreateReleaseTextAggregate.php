<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;

use function array_filter;
use function array_map;
use function implode;

final class AppendingCreateReleaseTextAggregate implements CreateReleaseText
{
    private const CONCATENATION_STRING = "\n\n-----\n\n";

    /** @psalm-var non-empty-array&CreateReleaseText[] */
    private array $strategies;

    /** @psalm-param CreateReleaseText[]&non-empty-array $strategies */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
    }

    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        $items = array_map(
            static fn ($strategy) => $strategy($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory),
            array_filter(
                $this->strategies,
                // phpcs:disable
                fn($strategy) => $strategy->canCreateReleaseText($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory)
                // phpcs:enable
            )
        );

        return implode(self::CONCATENATION_STRING, $items);
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        foreach ($this->strategies as $strategy) {
            if (
                $strategy->canCreateReleaseText(
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
