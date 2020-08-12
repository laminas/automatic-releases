<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Lcobucci\Clock\Clock;
use Phly\KeepAChangelog\Common\DiscoverChangelogEntryListener;
use Phly\KeepAChangelog\Config;
use Phly\KeepAChangelog\Version\ReadyLatestChangelogEvent;
use Phly\KeepAChangelog\Version\SetDateForChangelogReleaseListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Webmozart\Assert\Assert;

use function sprintf;

final class CommitReleaseChangelogViaKeepAChangelog implements CommitReleaseChangelog
{
    private const CHANGELOG_FILE = 'CHANGELOG.md';

    private const COMMIT_TEMPLATE = <<< 'COMMIT'
        %s readiness

        Updates the %s to set the release date.
        COMMIT;

    private Clock $clock;
    private ChangelogExists $changelogExists;
    private CheckoutBranch $checkoutBranch;
    private CommitFile $commitFile;
    private Push $push;
    private LoggerInterface $logger;

    public function __construct(
        Clock $clock,
        ChangelogExists $changelogExists,
        CheckoutBranch $checkoutBranch,
        CommitFile $commitFile,
        Push $push,
        LoggerInterface $logger
    ) {
        $this->clock           = $clock;
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
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch
    ): void {
        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            // No changelog
            $this->logger->info('No CHANGELOG.md file detected');

            return;
        }

        $changelogFile = sprintf('%s/%s', $repositoryDirectory, self::CHANGELOG_FILE);
        $versionString = $version->fullReleaseName();

        ($this->checkoutBranch)($repositoryDirectory, $sourceBranch);

        if (! $this->updateChangelog($changelogFile, $versionString)) {
            // Failure to update; nothing to commit
            return;
        }

        $message = sprintf(self::COMMIT_TEMPLATE, $versionString, self::CHANGELOG_FILE);
        Assert::notEmpty($message);

        ($this->commitFile)(
            $repositoryDirectory,
            $sourceBranch,
            self::CHANGELOG_FILE,
            $message
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());
    }

    private function updateChangelog(string $changelogFile, string $versionString): bool
    {
        $event = $this->createReadyLatestChangelogEvent($changelogFile, $versionString);

        (new DiscoverChangelogEntryListener())($event);

        if ($event->failed()) {
            $this->logger->info(sprintf(
                'Failed to find release version "%s" in "%s"',
                $versionString,
                $changelogFile
            ));

            return false;
        }

        (new SetDateForChangelogReleaseListener())($event);

        if ($event->failed()) {
            $this->logger->info(sprintf(
                'Failed setting release date for version "%s" in "%s"',
                $versionString,
                $changelogFile
            ));

            return false;
        }

        $this->logger->info(sprintf(
            'Set release date for version "%s" in "%s" to "%s"',
            $versionString,
            $changelogFile,
            $this->clock->now()->format('Y-m-d')
        ));

        return true;
    }

    private function createReadyLatestChangelogEvent(
        string $changelogFile,
        string $versionString
    ): ReadyLatestChangelogEvent {
        /**
         * Hard-coded extension to allow setting version to known value
         *
         * @psalm-suppress PropertyNotSetInConstructor
         */
        $event = new class ($this->clock, $versionString) extends ReadyLatestChangelogEvent {
            private string $releaseDate;
            private string $version;

            public function __construct(Clock $clock, string $versionString)
            {
                $this->releaseDate = $clock->now()->format('Y-m-d');
                $this->version     = $versionString;
                // Required as failure methods write to output
                $this->output = new NullOutput();
            }

            /**
             * Overridden as parent uses private property access.
             */
            public function version(): string
            {
                return $this->version;
            }

            /**
             * Overridden as parent uses private property access.
             */
            public function releaseDate(): string
            {
                return $this->releaseDate;
            }
        };

        $event->discoveredConfiguration($this->createKeepAChangelogConfig($changelogFile));

        return $event;
    }

    private function createKeepAChangelogConfig(string $changelogFile): Config
    {
        // Inline extension to allow hard-coding changelog file to what is known.
        return new class ($changelogFile) extends Config {
            private string $changelogFile;

            public function __construct(string $changelogFile)
            {
                parent::__construct();
                $this->changelogFile = $changelogFile;
            }

            public function changelogFile(): string
            {
                return $this->changelogFile;
            }
        };
    }
}
