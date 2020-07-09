<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_map;
use function assert;
use function explode;
use function is_string;
use function Safe\preg_replace;
use function trim;

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
            $sanitizedBranch = preg_replace(
                '~^(?:remotes/)?origin/~',
                '',
                trim($branch, "* \t\n\r\0\x0B")
            );
            assert(is_string($sanitizedBranch));

            return BranchName::fromName($sanitizedBranch);
        }, $branches));
    }
}
