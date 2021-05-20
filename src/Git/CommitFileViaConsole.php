<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl;
use Psl\Shell;
use Psl\Str;

final class CommitFileViaConsole implements CommitFile
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $filename,
        string $commitMessage,
        SecretKeyId $keyId
    ): void {
        $this->assertWeAreOnBranch($sourceBranch, $repositoryDirectory, $filename);

        Shell\execute('git', ['add', $filename], $repositoryDirectory);
        Shell\execute('git', ['commit', '-m', $commitMessage, '--gpg-sign=' . $keyId->id()], $repositoryDirectory);
    }

    /**
     * @param non-empty-string $repositoryDirectory
     * @param non-empty-string $filename
     */
    private function assertWeAreOnBranch(
        BranchName $expectedBranch,
        string $repositoryDirectory,
        string $filename
    ): void {
        $output = Str\trim(Shell\execute('git', ['branch', '--show-current'], $repositoryDirectory));

        Psl\invariant($output === $expectedBranch->name(), Str\format(
            'CommitFile: Cannot commit file %s to branch %s, as a different branch is currently checked out (%s).',
            $filename,
            $expectedBranch->name(),
            $output
        ));
    }
}
