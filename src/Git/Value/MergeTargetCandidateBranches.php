<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git\Value;

use Psl\Iter;
use Psl\Type;
use Psl\Vec;

use function array_search;

final class MergeTargetCandidateBranches
{
    /**
     * @var BranchName[] branches that can be used for releases, sorted in ascending version number
     * @psalm-var list<BranchName>
     */
    private array $sortedBranches;

    /**
     * @param BranchName[] $sortedBranches
     * @psalm-param list<BranchName> $sortedBranches
     */
    private function __construct(array $sortedBranches)
    {
        $this->sortedBranches = $sortedBranches;
    }

    public static function fromAllBranches(BranchName ...$branches): self
    {
        $mergeTargetBranches = Vec\filter($branches, static function (BranchName $branch): bool {
            return $branch->isReleaseBranch();
        });

        $mergeTargetBranches = Vec\sort($mergeTargetBranches, static function (BranchName $a, BranchName $b): int {
            return $a->majorAndMinor() <=> $b->majorAndMinor();
        });

        return new self($mergeTargetBranches);
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

        $lastBranch      = Type\object(BranchName::class)->assert(Iter\last($this->sortedBranches));
        $targetBranchKey = array_search($targetBranch, $this->sortedBranches, true);

        $branch = Type\int()->matches($targetBranchKey)
            ? ($this->sortedBranches[$targetBranchKey + 1] ?? $lastBranch)
            : $lastBranch;

        // If the target branch and the merge-up branch are the same, no merge-up is needed
        return $branch === $targetBranch
            ? null
            : $branch;
    }

    public function newestReleaseBranch(): ?BranchName
    {
        return Iter\first(Vec\reverse($this->sortedBranches));
    }

    public function newestFutureReleaseBranchAfter(SemVerVersion $version): BranchName
    {
        $nextMinor = $version->nextMinor();

        $futureReleaseBranch = Vec\filter(
            Vec\reverse($this->sortedBranches),
            static function (BranchName $branch) use ($nextMinor): bool {
                return $nextMinor->lessThanEqual($branch->targetMinorReleaseVersion());
            }
        );

        return Iter\first($futureReleaseBranch) ?? $nextMinor->targetReleaseBranchName();
    }

    public function contains(BranchName $needle): bool
    {
        return Iter\any(
            $this->sortedBranches,
            static fn (BranchName $branch): bool => $needle->equals($branch)
        );
    }
}
