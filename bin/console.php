#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\WebApplication;

use ErrorException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\Application\Command\CreateMergeUpPullRequest;
use Laminas\AutomaticReleases\Application\Command\ReleaseCommand;
use Laminas\AutomaticReleases\Application\Command\SwitchDefaultBranchToNextMinor;
use Laminas\AutomaticReleases\Changelog\CreateChangelogViaMilestone;
use Laminas\AutomaticReleases\Changelog\ReleaseChangelogAndFetchContentsAggregate;
use Laminas\AutomaticReleases\Changelog\UseKeepAChangelogEventsToReleaseAndFetchChangelog;
use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Git\CommitFileViaConsole;
use Laminas\AutomaticReleases\Git\CreateTagViaConsole;
use Laminas\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches;
use Laminas\AutomaticReleases\Git\PushViaConsole;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneFirst100IssuesAndPullRequests;
use Laminas\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall;
use Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall;
use Laminas\AutomaticReleases\Github\CreateReleaseTextThroughChangelog;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Laminas\AutomaticReleases\Github\JwageGenerateChangelog;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use PackageVersions\Versions;
use Phly\KeepAChangelog\EventDispatcher;
use Phly\KeepAChangelog\ListenerProvider;
use Symfony\Component\Console\Application;

use function set_error_handler;

use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;

(static function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function (int $errorCode, string $message = '', string $file = '', int $line = 0): bool {
            throw new ErrorException($message, 0, $errorCode, $file, $line);
        },
        E_STRICT | E_NOTICE | E_WARNING
    );

    $variables            = EnvironmentVariables::fromEnvironment(new ImportGpgKeyFromStringViaTemporaryFile());
    $loadEvent            = new LoadCurrentGithubEventFromGithubActionPath($variables);
    $fetch                = new FetchAndSetCurrentUserByReplacingCurrentOriginRemote($variables);
    $getCandidateBranches = new GetMergeTargetCandidateBranchesFromRemoteBranches();
    $makeRequests         = Psr17FactoryDiscovery::findRequestFactory();
    $httpClient           = HttpClientDiscovery::find();
    $githubToken          = $variables->githubToken();
    $getMilestone         = new GetMilestoneFirst100IssuesAndPullRequests(new RunGraphQLQuery(
        $makeRequests,
        $httpClient,
        $githubToken
    ));
    $push                 = new PushViaConsole();
    $createReleaseText    = new ReleaseChangelogAndFetchContentsAggregate([
        new UseKeepAChangelogEventsToReleaseAndFetchChangelog(
            new EventDispatcher(new ListenerProvider()),
            new CommitFileViaConsole(),
            $push
        ),
        new CreateChangelogViaMilestone(
            new CreateReleaseTextThroughChangelog(JwageGenerateChangelog::create(
                $makeRequests,
                $httpClient
            ))
        ),
    ]);
    $createRelease        = new CreateReleaseThroughApiCall(
        $makeRequests,
        $httpClient,
        $githubToken
    );

    /** @psalm-suppress DeprecatedClass */
    $application = new Application(Versions::ROOT_PACKAGE_NAME, Versions::getVersion('laminas/automatic-releases'));

    $application->addCommands([
        new ReleaseCommand(
            $variables,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $getMilestone,
            $createReleaseText,
            new CreateTagViaConsole(),
            $push,
            $createRelease
        ),
        new CreateMergeUpPullRequest(
            $variables,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $getMilestone,
            $createReleaseText,
            $push,
            new CreatePullRequestThroughApiCall(
                $makeRequests,
                $httpClient,
                $githubToken
            )
        ),
        new SwitchDefaultBranchToNextMinor(
            $variables,
            $loadEvent,
            $fetch,
            $getCandidateBranches,
            $push,
            new SetDefaultBranchThroughApiCall(
                $makeRequests,
                $httpClient,
                $githubToken
            )
        ),
    ]);

    $application->run();
})();
