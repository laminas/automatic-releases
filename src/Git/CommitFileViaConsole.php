<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function sprintf;
use function trim;

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

        (new Process(['git', 'add', $filename], $repositoryDirectory))
            ->mustRun();

        (new Process(
            ['git', 'commit', '-m', $commitMessage, '--gpg-sign=' . $keyId->id()],
            $repositoryDirectory
        ))->mustRun();
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
        $process = new Process(['git', 'branch', '--show-current'], $repositoryDirectory);
        $process->mustRun();

        $output = trim($process->getOutput());

        Assert::same($output, $expectedBranch->name(), sprintf(
            'CommitFile: Cannot commit file %s to branch %s, as a different branch is currently checked out (%s).',
            $filename,
            $expectedBranch->name(),
            $output
        ));
    }
}
