<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;

final class MergeTargetCandidateBranches
{
    /** @var BranchName[] */
    private $sortedBranches;

    private function __construct()
    {
    }

    public static function fromAllBranches(BranchName ...$branches) : self
    {
        $mergeTargetBranches = array_filter($branches, static function (BranchName $branch) : bool {
            return $branch->isReleaseBranch()
                || $branch->isNextMajor();
        });

        Assert::that($mergeTargetBranches)
              ->notEmpty();

        \Safe\usort($mergeTargetBranches, static function (BranchName $a, BranchName $b) : int {
            if ($a->isNextMajor()) {
                return 1;
            }

            if ($b->isNextMajor()) {
                return -1;
            }

            return $a->majorAndMinor() <=> $b->majorAndMinor();
        });

        $instance = new self();

        $instance->sortedBranches = $mergeTargetBranches;

        return $instance;
    }

    public function targetBranchFor(SemVerVersion $version) : ?BranchName
    {
        return array_values(array_filter(
                $this->sortedBranches,
                static function (BranchName $branch) use ($version) : bool {
                    return ! $branch->isNextMajor()
                        && $branch->majorAndMinor() === [$version->major(), $version->minor()];
                }
            ))[0] ?? null;
    }

    public function branchToMergeUp(SemVerVersion $version) : BranchName
    {
        $targetBranch = $this->targetBranchFor($version);
        $lastBranch   = end($this->sortedBranches);

        \assert($lastBranch instanceof BranchName);

        $targetBranchKey = array_search($targetBranch, $this->sortedBranches, true);

        return is_int($targetBranchKey)
            ? ($this->sortedBranches[$targetBranchKey + 1] ?? $lastBranch)
            : $lastBranch;
    }
}
