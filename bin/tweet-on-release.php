#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases;

use ErrorException;
use Laminas\AutomaticReleases\Application\Command\TweetReleaseCommand;
use Laminas\AutomaticReleases\Environment\GithubEnvironmentVariables;
use Laminas\AutomaticReleases\Environment\TwitterEnvironmentVariables;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEventFromGithubActionPath;
use Laminas\AutomaticReleases\Twitter\CreateTweetThroughApiCall;
use Laminas\Twitter\Twitter;
use PackageVersions\Versions;
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

    $twitterEnvironmentVariables = TwitterEnvironmentVariables::fromEnvironment();
    $githubEnvironmentVariables  = GithubEnvironmentVariables::fromEnvironment();
    $loadEvent                   = new LoadCurrentGithubEventFromGithubActionPath($githubEnvironmentVariables);

    // /** @psalm-suppress DeprecatedClass */
    $application = new Application(Versions::rootPackageName(), Versions::getVersion('laminas/automatic-releases'));

    $application->addCommands([
        new TweetReleaseCommand(
            $loadEvent,
            new CreateTweetThroughApiCall(
                new Twitter([
                    'access_token' => [
                        'token' => $twitterEnvironmentVariables->accessToken(),
                        'secret' => $twitterEnvironmentVariables->accessTokenSecret(),
                    ],
                    'oauth_options' => [
                        'consumerKey' => $twitterEnvironmentVariables->consumerApiKey(),
                        'consumerSecret' => $twitterEnvironmentVariables->consumerApiSecret(),
                    ],
                ])
            )
        ),
    ]);

    $application->run();
})();
