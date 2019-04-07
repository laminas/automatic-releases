<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git\Value;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;

final class MergeTargetCandidateBranchesTest extends TestCase
{
    public function test() : void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.4'),
            BranchName::fromName('1.2'),
            BranchName::fromName('master'),
            BranchName::fromName('1.0'),
            BranchName::fromName('a/b/c'), // filtered out
            BranchName::fromName('1.5')
        );

        self::assertNull($branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.99.0')));
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.99.0'))
        );


        self::assertNull($branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0')));
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0'))
        );

        self::assertEquals(
            BranchName::fromName('1.2'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.2.3'))
        );
        self::assertEquals(
            BranchName::fromName('1.4'), // note: there is no 1.3
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.2.3'))
        );

        self::assertEquals(
            BranchName::fromName('1.5'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.5.99'))
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.5.99'))
        );

        self::assertEquals(
            BranchName::fromName('1.0'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.0.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.1'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.0.1'))
        );
    }
}
