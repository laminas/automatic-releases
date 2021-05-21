<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git\Value;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\AssertException;

final class BranchNameTest extends TestCase
{
    /**
     * @dataProvider genericBranchNames
     */
    public function testAllowsAnyBranchName(string $inputName): void
    {
        $branch = BranchName::fromName($inputName);

        self::assertSame($inputName, $branch->name());
        self::assertFalse($branch->isReleaseBranch());
    }

    /** @return array<int, array<int, string>> */
    public function genericBranchNames(): array
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

    public function testDisallowsEmptyBranchName(): void
    {
        $this->expectException(AssertException::class);

        BranchName::fromName('');
    }

    /**
     * @dataProvider releaseBranches
     */
    public function testDetectsReleaseBranchVersions(string $inputName, int $major, int $minor): void
    {
        $branch = BranchName::fromName($inputName);

        self::assertTrue($branch->isReleaseBranch());
        self::assertSame([$major, $minor], $branch->majorAndMinor());
    }

    /**
     * @return array<int, array<int, int|string>>
     * @psalm-return array<int, array{0: string, 1: int, 2: int}>
     */
    public function releaseBranches(): array
    {
        return [
            ['1.2', 1, 2],
            ['v1.2', 1, 2],
            ['33.44.x', 33, 44],
        ];
    }

    public function testEquals(): void
    {
        self::assertFalse(BranchName::fromName('foo')->equals(BranchName::fromName('bar')));
        self::assertFalse(BranchName::fromName('bar')->equals(BranchName::fromName('foo')));
        self::assertTrue(BranchName::fromName('foo')->equals(BranchName::fromName('foo')));
    }

    /**
     * @dataProvider versionEqualityProvider
     */
    public function testIsForVersion(string $milestoneName, string $branchName, bool $expected): void
    {
        self::assertSame(
            $expected,
            BranchName::fromName($branchName)
                ->isForVersion(SemVerVersion::fromMilestoneName($milestoneName))
        );
    }

    /**
     * @return array<int, array<int, bool|string>>
     * @psalm-return array<int, array{0: string, 1: string, 2: bool}>
     */
    public function versionEqualityProvider(): array
    {
        return [
            ['1.0.0', '1.0.x', true],
            ['1.0.0', '1.1.x', false],
            ['1.0.0', '0.9.x', false],
            ['2.0.0', '1.0.x', false],
            ['2.0.0', '2.0.x', true],
            ['2.0.0', '2.0', true],
            ['2.0.0', '2.1', false],
        ];
    }

    /**
     * @dataProvider newerVersionComparisonProvider
     */
    public function testIsForNewerVersionThan(string $milestoneName, string $branchName, bool $expected): void
    {
        self::assertSame(
            $expected,
            BranchName::fromName($branchName)
                ->isForNewerVersionThan(SemVerVersion::fromMilestoneName($milestoneName))
        );
    }

    /**
     * @return array<int, array<int, bool|string>>
     * @psalm-return array<int, array{0: string, 1: string, 2: bool}>
     */
    public function newerVersionComparisonProvider(): array
    {
        return [
            ['1.0.0', '1.0.x', false],
            ['1.0.0', '1.1.x', true],
            ['1.0.0', '0.9.x', false],
            ['2.0.0', '1.0.x', false],
            ['2.0.0', '2.0.x', false],
            ['2.0.0', '2.0', false],
            ['2.0.0', '2.1', true],
            ['2.0.0', '1.9', false],
        ];
    }

    /**
     * @dataProvider targetVersionProvider
     */
    public function testTargetMinorReleaseVersion(string $branchName, string $expectedVersion): void
    {
        self::assertEquals(
            SemVerVersion::fromMilestoneName($expectedVersion),
            BranchName::fromName($branchName)
                ->targetMinorReleaseVersion()
        );
    }

    /**
     * @return string[][]
     * @psalm-return list<array{0: string, 1: string}>
     */
    public function targetVersionProvider(): array
    {
        return [
            ['2.0', '2.0.0'],
            ['2.0.x', '2.0.0'],
            ['2.1.x', '2.1.0'],
            ['1.99.x', '1.99.0'],
        ];
    }
}
