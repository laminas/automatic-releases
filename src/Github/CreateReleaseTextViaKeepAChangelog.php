<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Phly\KeepAChangelog\Common\ChangelogParser;
use Phly\KeepAChangelog\Exception\ExceptionInterface;

use function file_exists;
use function file_get_contents;

class CreateReleaseTextViaKeepAChangelog implements CreateReleaseText
{
    public function __invoke(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): string {
        /** @psalm-var non-empty-string $changelog */
        $changelog = (new ChangelogParser())
            ->findChangelogForVersion(
                file_get_contents($repositoryDirectory . '/CHANGELOG.md'),
                $semVerVersion->fullReleaseName()
            );

        return $changelog;
    }

    public function canCreateReleaseText(
        Milestone $milestone,
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion,
        BranchName $sourceBranch,
        string $repositoryDirectory
    ): bool {
        $changelogFile = $repositoryDirectory . '/CHANGELOG.md';
        if (! file_exists($changelogFile)) {
            return false;
        }

        try {
            (new ChangelogParser())
                ->findChangelogForVersion(
                    file_get_contents($changelogFile),
                    $semVerVersion->fullReleaseName()
                );

            return true;
        } catch (ExceptionInterface $e) {
            return false;
        }
    }
}
