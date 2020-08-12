<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Phly\KeepAChangelog\Common\ChangelogParser;
use Phly\KeepAChangelog\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

class CreateReleaseTextViaKeepAChangelog implements CreateReleaseText
{
    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        $changelog = (new ChangelogParser())
            ->findChangelogForVersion(
                $this->fetchChangelogContentsFromBranch($sourceBranch, $repositoryDirectory),
                $semVerVersion->fullReleaseName()
            );

        Assert::notEmpty($changelog);

        return $changelog;
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        if (! $this->changelogExistsInBranch($sourceBranch, $repositoryDirectory)) {
            return false;
        }

        try {
            $changelog = (new ChangelogParser())
                ->findChangelogForVersion(
                    $this->fetchChangelogContentsFromBranch($sourceBranch, $repositoryDirectory),
                    $semVerVersion->fullReleaseName()
                );

            Assert::notEmpty($changelog);

            return true;
        } catch (ExceptionInterface | InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param non-empty-string $repositoryDirectory
     */
    private function changelogExistsInBranch(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        $process = new Process(['git', 'show', $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     * @psalm-return non-empty-string
     */
    private function fetchChangelogContentsFromBranch(
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        $process = new Process(['git', 'show', $sourceBranch->name() . ':CHANGELOG.md'], $repositoryDirectory);
        $process->mustRun();

        $contents = $process->getOutput();
        Assert::notEmpty($contents);

        return $contents;
    }
}
