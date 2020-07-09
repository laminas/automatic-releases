<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git;

use Doctrine\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function mkdir;
use function Safe\tempnam;
use function sys_get_temp_dir;
use function unlink;

/** @covers \Doctrine\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches */
final class GetMergeTargetCandidateBranchesFromRemoteBranchesTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $source      = tempnam(sys_get_temp_dir(), 'GetMergeTargetSource');
        $destination = tempnam(sys_get_temp_dir(), 'GetMergeTargetDestination');

        Assert::notEmpty($source);
        Assert::notEmpty($destination);

        $this->source      = $source;
        $this->destination = $destination;

        unlink($this->source);
        unlink($this->destination);
        mkdir($this->source);

        (new Process(['git', 'init'], $this->source))
            ->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $this->source))
            ->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $this->source))
            ->mustRun();
        (new Process(['git', 'remote', 'add', 'origin', $this->destination], $this->source))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'a commit'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'ignored-branch'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', '1.0.x'], $this->source))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', '2.1.x'], $this->source))
            ->mustRun();
        (new Process(['git', 'clone', $this->source, $this->destination]))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'new-ignored-branch'], $this->source))
            ->mustRun();
        // Ignored - wasn't fetched
        (new Process(['git', 'checkout', '-b', '3.0.x'], $this->source))
            ->mustRun();
        // Ignored - not on remote
        (new Process(['git', 'checkout', '-b', '4.0.x'], $this->destination))
            ->mustRun();
    }

    public function testFetchesMergeTargetCandidates(): void
    {
        self::assertEquals(
            MergeTargetCandidateBranches::fromAllBranches(
                BranchName::fromName('1.0.x'),
                BranchName::fromName('2.1.x'),
                BranchName::fromName('master'),
            ),
            (new GetMergeTargetCandidateBranchesFromRemoteBranches())
                ->__invoke($this->destination)
        );
    }
}
