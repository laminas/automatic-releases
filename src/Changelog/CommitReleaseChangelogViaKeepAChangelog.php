<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Str;
use Psl\Type;
use Psr\Log\LoggerInterface;

final readonly class CommitReleaseChangelogViaKeepAChangelog implements CommitReleaseChangelog
{
    private const CHANGELOG_FILE = 'CHANGELOG.md';

    private const COMMIT_TEMPLATE = <<< 'COMMIT'
        %s readiness

        Updates the %s to set the release date.
        COMMIT;

    public function __construct(
        private readonly ChangelogExists $changelogExists,
        private readonly CheckoutBranch $checkoutBranch,
        private readonly CommitFile $commitFile,
        private readonly Push $push,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @psalm-param non-empty-string $repositoryDirectory */
    public function __invoke(
        ChangelogReleaseNotes $releaseNotes,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch,
        SecretKeyId $keyId,
    ): void {
        if (! $releaseNotes->requiresUpdatingChangelogFile()) {
            // Nothing to commit
            $this->logger->info('CommitReleaseChangelog: no changes to commit.');

            return;
        }

        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            // No changelog
            $this->logger->info('CommitReleaseChangelog: No CHANGELOG.md file detected');

            return;
        }

        ($this->checkoutBranch)($repositoryDirectory, $sourceBranch);

        $changelogFile = Type\non_empty_string()
            ->assert(Str\format('%s/%s', $repositoryDirectory, self::CHANGELOG_FILE));

        $releaseNotes::writeChangelogFile($changelogFile, $releaseNotes);

        $message = Type\non_empty_string()
            ->assert(Str\format(self::COMMIT_TEMPLATE, $version->fullReleaseName(), self::CHANGELOG_FILE));

        ($this->commitFile)(
            $repositoryDirectory,
            $sourceBranch,
            self::CHANGELOG_FILE,
            $message,
            $keyId,
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());
    }
}
