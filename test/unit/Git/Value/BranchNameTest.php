<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git\Value;

use Assert\AssertionFailedException;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\TestCase;

final class BranchNameTest extends TestCase
{
    /** @dataProvider genericBranchNames */
    public function testAllowsAnyBranchName(string $inputName) : void
    {
        $branch = BranchName::fromName($inputName);

        self::assertSame($inputName, $branch->name());
        self::assertFalse($branch->isReleaseBranch());
        self::assertFalse($branch->isNextMajor());
    }

    /** @return array<int, array<int, string>> */
    public static function genericBranchNames() : array
    {
        return [
            ['foo'],
            ['a1.2.'],
            ['1.2.'],
            ['a1.2.3'],
            ['1.2.3.4'],
            ['foo/bar'],
            ['foo-bar-baz'],
            ['a/b/c/1/2/3'],
        ];
    }

    public function testDisallowsEmptyBranchName() : void
    {
        $this->expectException(AssertionFailedException::class);

        BranchName::fromName('');
    }

    public function testMasterIsNextMajorRelease() : void
    {
        $branch = BranchName::fromName('master');

        self::assertTrue($branch->isNextMajor());
        self::assertFalse($branch->isReleaseBranch());
    }

    /** @dataProvider releaseBranches */
    public function testDetectsReleaseBranchVersions(string $inputName, int $major, int $minor) : void
    {
        $branch = BranchName::fromName($inputName);

        self::assertFalse($branch->isNextMajor());
        self::assertTrue($branch->isReleaseBranch());
        self::assertSame([$major, $minor], $branch->majorAndMinor());
    }

    /** @return array<int, array<int, int|string>> */
    public static function releaseBranches() : array
    {
        return [
            ['1.2', 1, 2],
            ['v1.2', 1, 2],
            ['33.44.x', 33, 44],
        ];
    }
}
