<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CheckoutBranchInterface;
use Laminas\AutomaticReleases\Git\CommitFileInterface;
use Laminas\AutomaticReleases\Git\PushInterface;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Str;
use Psl\Type;
use Psr\Log\LoggerInterface;

final class CommitReleaseChangelogViaKeepAChangelog implements CommitReleaseChangelogInterface
{
    private const CHANGELOG_FILE = 'CHANGELOG.md';

    private const COMMIT_TEMPLATE = <<<'COMMIT'
        %s readiness

        Updates the %s to set the release date.
        COMMIT;

    private ChangelogExistsInterface $changelogExists;
    private CheckoutBranchInterface $checkoutBranch;
    private CommitFileInterface $commitFile;
    private PushInterface $push;
    private LoggerInterface $logger;

    public function __construct(
        ChangelogExistsInterface $changelogExists,
        CheckoutBranchInterface $checkoutBranch,
        CommitFileInterface $commitFile,
        PushInterface $push,
        LoggerInterface $logger
    ) {
        $this->changelogExists = $changelogExists;
        $this->checkoutBranch  = $checkoutBranch;
        $this->commitFile      = $commitFile;
        $this->push            = $push;
        $this->logger          = $logger;
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        ChangelogReleaseNotes $releaseNotes,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch,
        SecretKeyId $keyId
    ): void {
        if (! $releaseNotes->requiresUpdatingChangelogFile()) {
            // Nothing to commit
            $this->logger->info('CommitReleaseChangelogInterface: no changes to commit.');

            return;
        }

        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            // No changelog
            $this->logger->info('CommitReleaseChangelogInterface: No CHANGELOG.md file detected');

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
            $keyId
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());
    }
}
