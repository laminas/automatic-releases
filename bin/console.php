#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\WebApplication;

use Doctrine\AutomaticReleases\Application\Command\CreateMergeUpPullRequest;
use Doctrine\AutomaticReleases\Application\Command\ReleaseCommand;
use Doctrine\AutomaticReleases\Environment\EnvironmentVariables;
use Doctrine\AutomaticReleases\Git\CreateTagViaConsole;
use Doctrine\AutomaticReleases\Git\FetchAndSetCurrentUserByReplacingCurrentOriginRemote;
use Doctrine\AutomaticReleases\Git\GetMergeTargetCandidateBranchesFromRemoteBranches;
use Doctrine\AutomaticReleases\Git\PushViaConsole;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneFirst100IssuesAndPullRequests;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Doctrine\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall;
use Doctrine\AutomaticReleases\Github\CreateReleaseTextThroughChangelog;
use Doctrine\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Doctrine\AutomaticReleases\Github\JwageGenerateChangelog;
use Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use ErrorException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use PackageVersions\Versions;
use Symfony\Component\Console\Application;
use function set_error_handler;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;

(static function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function ($errorCode, $message = '', $file = '', $line = 0): bool {
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
    $createReleaseText    = new CreateReleaseTextThroughChangelog(JwageGenerateChangelog::create(
        $makeRequests,
        $httpClient
    ));
    $push                 = new PushViaConsole();
    $createRelease        = new CreateReleaseThroughApiCall(
        $makeRequests,
        $httpClient,
        $githubToken
    );

    $application = new Application('doctrine/automatic-releases', Versions::getVersion('doctrine/automatic-releases'));

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
    ]);

    $application->run();
})();
