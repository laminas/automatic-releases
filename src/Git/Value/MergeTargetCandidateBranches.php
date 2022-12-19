<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git\Value;

use Psl\Iter;
use Psl\Type;
use Psl\Vec;

use function array_search;
use function Psl\Iter\first;
use function Psl\Vec\filter;
use function Psl\Vec\reverse;

final class MergeTargetCandidateBranches
{
    /**
     * @param BranchName[] $sortedBranches branches that can be used for releases, sorted in ascending version number
     * @psalm-param list<BranchName> $sortedBranches
     */
    private function __construct(private readonly array $sortedBranches)
    {
    }

    public static function fromAllBranches(BranchName ...$branches): self
    {
        $mergeTargetBranches = filter($branches, static function (BranchName $branch): bool {
            return $branch->isReleaseBranch();
        });

        $mergeTargetBranches = Vec\sort($mergeTargetBranches, self::branchOrder(...));

        return new self($mergeTargetBranches);
    }

    public function targetBranchFor(SemVerVersion $version): BranchName|null
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

    public function branchToMergeUp(SemVerVersion $version): BranchName|null
    {
        $targetBranch = $this->targetBranchFor($version);

        if ($targetBranch === null) {
            // There's no branch where we can merge this, so we can't merge up either
            return null;
        }

        $lastBranch      = Type\instance_of(BranchName::class)->assert(Iter\last($this->sortedBranches));
        $targetBranchKey = array_search($targetBranch, $this->sortedBranches, true);

        $branch = Type\int()->matches($targetBranchKey)
            ? ($this->sortedBranches[$targetBranchKey + 1] ?? $lastBranch)
            : $lastBranch;

        // If the target branch and the merge-up branch are the same, no merge-up is needed
        return $branch === $targetBranch
            ? null
            : $branch;
    }

    public function newestReleaseBranch(): BranchName|null
    {
        return first(reverse($this->sortedBranches));
    }

    public function newestFutureReleaseBranchAfter(SemVerVersion $version): BranchName
    {
        $nextMinor = $version->nextMinor();

        /** @var ?BranchName $futureReleaseBranch */
        $futureReleaseBranch = first(filter(
            reverse($this->sortedBranches),
            static function (BranchName $branch) use ($nextMinor): bool {
                $targetVersion = $branch->targetMinorReleaseVersion();

                return ! $targetVersion->isNewMajorRelease() && $nextMinor->lessThanEqual($targetVersion);
            },
        ));

        return $futureReleaseBranch ?? $nextMinor->targetReleaseBranchName();
    }

    public function contains(BranchName $needle): bool
    {
        return Iter\any(
            $this->sortedBranches,
            static fn (BranchName $branch): bool => $needle->equals($branch)
        );
    }

    /** @return -1|0|1 */
    private static function branchOrder(BranchName $a, BranchName $b): int
    {
        return $a->majorAndMinor() <=> $b->majorAndMinor();
    }
}
