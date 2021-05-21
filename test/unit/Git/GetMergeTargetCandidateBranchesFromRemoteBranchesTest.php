<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Type;

/** @covers \Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches */
final class GetMergeTargetCandidateBranchesFromRemoteBranchesTest extends TestCase
{
    /** @psalm-var non-empty-string */
    private string $source;
    /** @psalm-var non-empty-string */
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $source      = Filesystem\create_temporary_file(Env\temp_dir(), 'GetMergeTargetSource');
        $destination = Filesystem\create_temporary_file(Env\temp_dir(), 'GetMergeTargetDestination');

        Type\non_empty_string()->assert($source);
        Type\non_empty_string()->assert($destination);

        $this->source      = $source;
        $this->destination = $destination;

        Filesystem\delete_file($this->source);
        Filesystem\delete_file($this->destination);
        Filesystem\create_directory($this->source);

        Shell\execute('git', ['init'], $this->source);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $this->source);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $this->source);
        Shell\execute('git', ['remote', 'add', 'origin', $this->destination], $this->source);
        Shell\execute('git', ['commit', '--allow-empty', '-m', 'a commit'], $this->source);
        Shell\execute('git', ['checkout', '-b', 'ignored-branch'], $this->source);
        Shell\execute('git', ['checkout', '-b', '1.0.x'], $this->source);
        Shell\execute('git', ['checkout', '-b', '2.1.x'], $this->source);
        Shell\execute('git', ['clone', $this->source, $this->destination]);
        Shell\execute('git', ['checkout', '-b', 'new-ignored-branch'], $this->source);
        // Ignored - wasn't fetched
        Shell\execute('git', ['checkout', '-b', '3.0.x'], $this->source);
        // Ignored - not on remote
        Shell\execute('git', ['checkout', '-b', '4.0.x'], $this->destination);
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
