<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git\Value;

use function array_filter;
use function array_reverse;
use function array_search;
use function array_values;
use function assert;
use function end;
use function is_int;
use function Safe\usort;

final class MergeTargetCandidateBranches
{
    /**
     * @var BranchName[] branches that can be used for releases, sorted in ascending version number
     * @psalm-var list<BranchName>
     */
    private array $sortedBranches;

    /**
     * @param BranchName[] $sortedBranches
     *
     * @psalm-param list<BranchName> $sortedBranches
     */
    private function __construct(array $sortedBranches)
    {
        $this->sortedBranches = $sortedBranches;
    }

    public static function fromAllBranches(BranchName ...$branches): self
    {
        $mergeTargetBranches = array_filter($branches, static function (BranchName $branch): bool {
            return $branch->isReleaseBranch();
        });

        usort($mergeTargetBranches, static function (BranchName $a, BranchName $b): int {
            return $a->majorAndMinor() <=> $b->majorAndMinor();
        });

        /** @psalm-var non-empty-list<BranchName> $mergeTargetBranches */
        return new self(array_values($mergeTargetBranches));
    }

    public function targetBranchFor(SemVerVersion $version): ?BranchName
    {
        foreach ($this->sortedBranches as $branch) {
            if ($branch->isForNewerVersionThan($version)) {
                return null;
            }

            if ($branch->isForVersion($version)) {
                return $branch;
            }
        }

        return null;
    }

    public function branchToMergeUp(SemVerVersion $version): ?BranchName
    {
        $targetBranch = $this->targetBranchFor($version);

        if ($targetBranch === null) {
            // There's no branch where we can merge this, so we can't merge up either
            return null;
        }

        $lastBranch = end($this->sortedBranches);

        assert($lastBranch instanceof BranchName);

        $targetBranchKey = array_search($targetBranch, $this->sortedBranches, true);

        $branch = is_int($targetBranchKey)
            ? ($this->sortedBranches[$targetBranchKey + 1] ?? $lastBranch)
            : $lastBranch;

        // If the target branch and the merge-up branch are the same, no merge-up is needed
        return $branch === $targetBranch
            ? null
            : $branch;
    }

    public function newestReleaseBranch(): ?BranchName
    {
        return array_reverse($this->sortedBranches)[0] ?? null;
    }

    public function newestFutureReleaseBranchAfter(SemVerVersion $version): BranchName
    {
        $nextMinor = $version->nextMinor();

        return array_filter(
            array_reverse($this->sortedBranches),
            static function (BranchName $branch) use ($nextMinor): bool {
                return $nextMinor->lessThanEqual($branch->targetMinorReleaseVersion());
            }
        )[0] ?? $nextMinor->targetReleaseBranchName();
    }

    public function contains(BranchName $needle): bool
    {
        return (bool) array_filter(
            $this->sortedBranches,
            static fn (BranchName $branch): bool => $needle->equals($branch)
        );
    }
}
