<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Symfony\Component\Process\Process;
use function Safe\preg_replace;

final class GetMergeTargetCandidateBranchesFromRemoteBranches implements GetMergeTargetCandidateBranches
{
    public function __invoke(string $repositoryRootDirectory): MergeTargetCandidateBranches
    {
        $branches = array_filter(explode(
            "\n",
            (new Process(['git', 'branch', '-r'], $repositoryRootDirectory))
                ->mustRun()
                ->getOutput()
        ));

        return MergeTargetCandidateBranches::fromAllBranches(...array_map(static function (string $branch): BranchName {
            /** @var string $sanitizedBranch */
            $sanitizedBranch = preg_replace(
                '~^(?:remotes/)?origin/~',
                '',
                trim($branch, "* \t\n\r\0\x0B")
            );

            return BranchName::fromName($sanitizedBranch);
        }, $branches));
    }
}
