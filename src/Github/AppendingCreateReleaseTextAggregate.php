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

final class AppendingCreateReleaseTextAggregate implements CreateReleaseText
{
    private const CONCATENATION_STRING = "\n\n-----\n\n";

    /** @var CreateReleaseText[] */
    private array $strategies;

    /** @param CreateReleaseText[] $strategies */
    public function __construct(array $strategies)
    {
        Assert::notEmpty($strategies);
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
            static fn (CreateReleaseText $strategy): string => $strategy($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory),
            array_filter(
                $this->strategies,
                // phpcs:disable
                fn(CreateReleaseText $strategy): bool => $strategy->canCreateReleaseText($milestone, $repositoryName, $semVerVersion, $sourceBranch, $repositoryDirectory)
                // phpcs:enable
            )
        );

        /** @psalm-var non-empty-string $changelog */
        $changelog = implode(self::CONCATENATION_STRING, $items);

        return $changelog;
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
