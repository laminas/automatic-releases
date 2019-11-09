<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git\Value;

use Assert\AssertionFailedException;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;

final class SemVerVersionTest extends TestCase
{
    /**
     * @dataProvider detectableReleases
     */
    public function testDetectedReleaseVersions(
        string $milestoneName,
        int $expectedMajor,
        int $expectedMinor,
        string $expectedVersionName
    ) : void {
        $version = SemVerVersion::fromMilestoneName($milestoneName);

        self::assertSame($expectedMajor, $version->major());
        self::assertSame($expectedMinor, $version->minor());
        self::assertSame($expectedVersionName, $version->fullReleaseName());
    }

    /**
     * @return array<int, array<int, int|string>>
     *
     * @psalm-return array<int, array{0: string, 1: int, 2: int, 3: string}>
     */
    public function detectableReleases() : array
    {
        return [
            ['1.2.3', 1, 2, '1.2.3'],
            ['v1.2.3', 1, 2, '1.2.3'],
            ['v4.3.2', 4, 3, '4.3.2'],
            ['v44.33.22', 44, 33, '44.33.22'],
        ];
    }

    /**
     * @dataProvider invalidReleases
     */
    public function testRejectsInvalidReleaseStrings(string $invalid) : void
    {
        $this->expectException(AssertionFailedException::class);

        SemVerVersion::fromMilestoneName($invalid);
    }

    /** @return array<int, array<int, string>> */
    public function invalidReleases() : array
    {
        return [
            ['1.2.3.4'],
            ['v1.2.3.4'],
            ['x1.2.3'],
            ['1.2.3 '],
            [' 1.2.3'],
            [''],
            ['potato'],
            ['1.2.'],
            ['1.2'],
        ];
    }

    /**
     * @dataProvider releaseBranchNames
     */
    public function testReleaseBranchNames(string $milestoneName, string $expectedTargetBranch) : void
    {
        self::assertEquals(
            BranchName::fromName($expectedTargetBranch),
            SemVerVersion::fromMilestoneName($milestoneName)
                ->targetReleaseBranchName()
        );
    }

    /** @return array<int, array<int, string>> */
    public function releaseBranchNames() : array
    {
        return [
            ['1.2.3', '1.2.x'],
            ['2.0.0', '2.0.x'],
            ['99.99.99', '99.99.x'],
        ];
    }
}
