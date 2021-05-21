<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Psl\Regex;
use Psl\Shell;
use Psl\Str;
use Psl\Vec;

final class GetMergeTargetCandidateBranchesFromRemoteBranches implements GetMergeTargetCandidateBranches
{
    public function __invoke(string $repositoryRootDirectory): MergeTargetCandidateBranches
    {
        $branches = Vec\filter(Str\split(
            Shell\execute('git', ['branch', '-r'], $repositoryRootDirectory),
            "\n",
        ));

        return MergeTargetCandidateBranches::fromAllBranches(...Vec\map($branches, static function (string $branch): BranchName {
            $sanitizedBranch = Regex\replace(
                Str\trim($branch, "* \t\n\r\0\x0B"),
                '~^(?:remotes/)?origin/~',
                ''
            );

            return BranchName::fromName($sanitizedBranch);
        }));
    }
}
