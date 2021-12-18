#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\WebApplication;

use Abraham\TwitterOAuth\TwitterOAuth;
use ChangelogGenerator\GitHubOAuthToken;
use DateTimeZone;
use ErrorException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\Application\Command\BumpChangelogForReleaseBranch;
use Laminas\AutomaticReleases\Application\Command\CreateMergeUpPullRequest;
use Laminas\AutomaticReleases\Application\Command\CreateMilestones;
use Laminas\AutomaticReleases\Application\Command\ReleaseCommand;
use Laminas\AutomaticReleases\Application\Command\SwitchDefaultBranchToNextMinor;
use Laminas\AutomaticReleases\Changelog\BumpAndCommitChangelogVersionViaKeepAChangelog;
use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Changelog\CommitReleaseChangelogViaKeepAChangelog;
use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Git\CheckoutBranchViaConsole;
use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\CreateTagViaConsole;
use Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches;
use Laminas\AutomaticReleases\Git\PushViaConsole;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneFirst100IssuesAndPullRequests;
use Laminas\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Laminas\AutomaticReleases\Github\Api\V3\CreateMilestoneThroughApiCall;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall;
use Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall;
use Laminas\AutomaticReleases\Github\CreateReleaseTextThroughChangelog;
use Laminas\AutomaticReleases\Github\CreateReleaseTextViaKeepAChangelog;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Laminas\AutomaticReleases\Github\JwageGenerateChangelog;
use Laminas\AutomaticReleases\Github\MergeMultipleReleaseNotes;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Twitter\PublishTweet;
use Lcobucci\Clock\SystemClock;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PackageVersions\Versions;
use Symfony\Component\Console\Application;

use function set_error_handler;

use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use const STDERR;

(static function (): void {
    /** @psalm-suppress MissingFile */
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function (int $errorCode, string $message = '', string $file = '', int $line = 0): bool {
            throw new ErrorException($message, 0, $errorCode, $file, $line);
        },
        E_STRICT | E_NOTICE | E_WARNING
    );

    $environment          = EnvironmentVariables::fromEnvironmentWithGpgKey(
        new ImportGpgKeyFromStringViaTemporaryFile()
    );
    $logger               = (new Logger('automatic-releases'))->pushHandler(
        new StreamHandler(STDERR, $environment->logLevel())
    );
    $loadEvent            = new LoadCurrentGithubEventFromGithubActionPath($environment);
    $fetch                = new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($environment);
    $getCandidateBranches = new GetMergeTargetCandidateBranchesFromRemoteBranches();
    $makeRequests         = Psr17FactoryDiscovery::findRequestFactory();
    $httpClient           = HttpClientDiscovery::find();
    $githubToken          = $environment->githubToken();
    $getMilestone         = new GetMilestoneFirst100IssuesAndPullRequests(new RunGraphQLQuery(
        $makeRequests,
        $httpClient,
        $githubToken
    ));
    $changelogExists      = new ChangelogExistsViaConsole();
    $checkoutBranch       = new CheckoutBranchViaConsole();
    $commit               = new CommitFileViaConsole();
    $push                 = new PushViaConsole();
    $commitChangelog      = new CommitReleaseChangelogViaKeepAChangelog(
        $changelogExists,
        $checkoutBranch,
        $commit,
        $push,
        $logger
    );
    $createCommitText     = new CreateReleaseTextThroughChangelog(JwageGenerateChangelog::create(
        $makeRequests,
        $httpClient,
        new GitHubOAuthToken($githubToken)
    ));
    $createReleaseText    = new MergeMultipleReleaseNotes([
        new CreateReleaseTextViaKeepAChangelog(
            $changelogExists,
            new SystemClock(new DateTimeZone('UTC'))
        ),
        $createCommitText,
    ]);
    $createRelease        = new CreateReleaseThroughApiCall(
        $makeRequests,
        $httpClient,
        $githubToken
    );

    $bumpChangelogVersion = new BumpAndCommitChangelogVersionViaKeepAChangelog(
        $changelogExists,
        $checkoutBranch,
        $commit,
        $push,
        $logger
    );

    $application = new Application(Versions::rootPackageName(), Versions::getVersion('laminas/automatic-releases'));
    $application->addCommands([
        new ReleaseCommand(
            $environment,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $getMilestone,
            $commitChangelog,
            $createReleaseText,
            new CreateTagViaConsole(),
            $push,
            $createRelease,
            new PublishTweet(
                new TwitterOAuth(
                    $environment->twitterConsumerApiKey(),
                    $environment->twitterConsumerApiSecret(),
                    $environment->twitterAccessToken(),
                    $environment->twitterAccessTokenSecret()
                ),
                $environment
            )
        ),
        new CreateMergeUpPullRequest(
            $environment,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $getMilestone,
            $createCommitText,
            $push,
            new CreatePullRequestThroughApiCall(
                $makeRequests,
                $httpClient,
                $githubToken
            )
        ),
        new SwitchDefaultBranchToNextMinor(
            $environment,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $push,
            new SetDefaultBranchThroughApiCall(
                $makeRequests,
                $httpClient,
                $githubToken
            ),
            $bumpChangelogVersion
        ),
        new BumpChangelogForReleaseBranch(
            $environment,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $bumpChangelogVersion
        ),
        new CreateMilestones(
            $loadEvent,
            new CreateMilestoneThroughApiCall(
                $makeRequests,
                $httpClient,
                $githubToken,
                $logger
            )
        ),
    ]);

    $application->run();
})();
