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
use Webmozart\Assert\Assert;

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
        $changelog = (new ChangelogParser())
            ->findChangelogForVersion(
                file_get_contents($repositoryDirectory . '/CHANGELOG.md'),
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
        $changelogFile = $repositoryDirectory . '/CHANGELOG.md';
        if (! file_exists($changelogFile)) {
            return false;
        }

        try {
            $changelog = (new ChangelogParser())
                ->findChangelogForVersion(
                    file_get_contents($changelogFile),
                    $semVerVersion->fullReleaseName()
                );

            Assert::notEmpty($changelog);

            return true;
        } catch (ExceptionInterface | InvalidArgumentException $e) {
            return false;
        }
    }
}
