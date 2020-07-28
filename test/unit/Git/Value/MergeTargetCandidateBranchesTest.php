<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git\Value;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;

final class MergeTargetCandidateBranchesTest extends TestCase
{
    public function test(): void
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
        self::assertNull($branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.99.0')));

        self::assertNull($branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0')));
        self::assertNull($branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0')));

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
        self::assertNull($branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.5.99')));
        self::assertNull($branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.6.0')));

        self::assertEquals(
            BranchName::fromName('1.0'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.0.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.1'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.0.1'))
        );
    }

    public function testCannotGetNextMajorBranchIfNoneExists(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.2'),
            BranchName::fromName('potato')
        );

        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.6.0')),
            'Cannot release next minor, since next minor branch does not exist'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.6.0')),
            'Cannot merge up next minor, since no next branch exists'
        );
        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0')),
            'Cannot release next major, since next major branch does not exist'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0')),
            'Cannot merge up next major, since no next branch exists'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.2.1')),
            'Cannot merge up: no master branch exists'
        );
    }

    /** @link https://github.com/doctrine/automatic-releases/pull/23#discussion_r344499867 */
    public function testWillNotPickTargetIfNoMatchingReleaseBranchAndNewerReleaseBranchesExist(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.2.x'),
            BranchName::fromName('master')
        );

        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.1.0')),
            '1.1.0 can\'t have a target branch, since 1.2.x already exists'
        );
    }

    /** @link https://github.com/doctrine/automatic-releases/pull/23#discussion_r344499867 */
    public function testWillNotPickPatchTargetIfNoMatchingReleaseBranchAndNewerReleaseBranchesExist(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.0.x'),
            BranchName::fromName('master')
        );

        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.1.1')),
            '1.1.1 can\'t have a target branch, since 1.1.x doesn\'t exist, but patches require a release branch'
        );
    }

    public function testWillComputeFutureReleaseBranchFromCurrentRelease(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1.x'),
            BranchName::fromName('1.4.x'),
            BranchName::fromName('1.2.x'),
        );

        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.0.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.1.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.1.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.2.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.3.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.3.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.5.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.4.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.6.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.5.0'))
        );
        self::assertEquals(
            BranchName::fromName('2.1.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('2.0.0'))
        );
    }

    public function testWillIgnoreMasterBranchWhenComputingFutureReleaseBranchName(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1.x'),
            BranchName::fromName('1.4.x'),
            BranchName::fromName('1.2.x'),
            BranchName::fromName('master'),
        );

        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.0.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.1.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.1.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.2.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.3.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.4.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.3.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.5.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.4.0'))
        );
        self::assertEquals(
            BranchName::fromName('1.6.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('1.5.0'))
        );
        self::assertEquals(
            BranchName::fromName('2.1.x'),
            $branches->newestFutureReleaseBranchAfter(SemVerVersion::fromMilestoneName('2.0.0'))
        );
    }

    public function testContains(): void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.1.x'),
            BranchName::fromName('1.2.x'),
        );

        self::assertTrue($branches->contains(BranchName::fromName('1.1')));
        self::assertTrue($branches->contains(BranchName::fromName('1.1.x')));
        self::assertTrue($branches->contains(BranchName::fromName('1.2.x')));
        self::assertFalse($branches->contains(BranchName::fromName('1.1.1.x')));
        self::assertFalse($branches->contains(BranchName::fromName('1.1.0')));
        self::assertFalse($branches->contains(BranchName::fromName('v1.1')));
        self::assertFalse($branches->contains(BranchName::fromName('v1.1.x')));
        self::assertFalse($branches->contains(BranchName::fromName('1.2')));
    }

    public function testNewestReleaseBranch(): void
    {
        self::assertEquals(
            BranchName::fromName('1.2.x'),
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.1'),
                BranchName::fromName('1.1.x'),
                BranchName::fromName('1.2.x'),
            )->newestReleaseBranch()
        );

        self::assertEquals(
            BranchName::fromName('1.4.x'),
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.1'),
                BranchName::fromName('1.1.x'),
                BranchName::fromName('1.4.x'),
                BranchName::fromName('1.2.x'),
            )->newestReleaseBranch()
        );

        self::assertEquals(
            BranchName::fromName('2.0.x'),
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.1'),
                BranchName::fromName('1.1.x'),
                BranchName::fromName('1.4.x'),
                BranchName::fromName('1.2.x'),
                BranchName::fromName('2.0.x'),
            )->newestReleaseBranch()
        );

        self::assertEquals(
            BranchName::fromName('2.0.x'),
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.1.x'),
                BranchName::fromName('2.0.x'),
                BranchName::fromName('master'),
            )->newestReleaseBranch()
        );

        self::assertNull(
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('foo'),
                BranchName::fromName('develop'),
                BranchName::fromName('master')
            )->newestReleaseBranch()
        );
    }
}
