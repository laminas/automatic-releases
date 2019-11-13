<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\WebApplication;

use Assert\Assert;
use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Doctrine\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateRelease;
use Doctrine\AutomaticReleases\Github\CreateChangelogText;
use Doctrine\AutomaticReleases\Github\Event\LoadCurrentGithubEventFromGithubActionPath;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Doctrine\AutomaticReleases\Github\JwageGenerateChangelog;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use ErrorException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tideways\Profiler;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use function array_filter;
use function array_map;
use function assert;
use function class_exists;
use function explode;
use function is_array;
use function Safe\file_put_contents;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\sprintf;
use function Safe\tempnam;
use function set_error_handler;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

// @TODO probably best to turn this into a symfony/console app
(static function () : void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function ($errorCode, $message = '', $file = '', $line = 0) : bool {
            throw new ErrorException($message, 0, $errorCode, $file, $line);
        },
        E_STRICT | E_NOTICE | E_WARNING
    );

    $buildDir = __DIR__ . '/../build';

    $cleanBuildDir = static function () use ($buildDir) : void {
        (new Process(['rm', '-rf', $buildDir]))
            ->mustRun();

        (new Process(['mkdir', $buildDir]))
            ->mustRun();
    };

    $cloneRepository = static function (
        UriInterface $repositoryUri,
        string $targetPath,
        string $gitAuthorName,
        string $gitAuthorEmail
    ) : void {
        (new Process(['git', 'clone', $repositoryUri->__toString(), $targetPath]))
            ->mustRun();

        (new Process(['git', 'config', 'user.email', $gitAuthorEmail], $targetPath))
            ->mustRun();

        (new Process(['git', 'config', 'user.name', $gitAuthorName], $targetPath))
            ->mustRun();
    };

    $getBranches = static function (string $repositoryDirectory) : MergeTargetCandidateBranches {
        (new Process(['git', 'fetch'], $repositoryDirectory))
            ->mustRun();

        $branches = array_filter(explode(
            "\n",
            (new Process(['git', 'branch', '-r'], $repositoryDirectory))
                ->mustRun()
                ->getOutput()
        ));

        return MergeTargetCandidateBranches::fromAllBranches(...array_map(static function (string $branch) : BranchName {
            /** @var string $sanitizedBranch */
            $sanitizedBranch = preg_replace(
                '~^(?:remotes/)?origin/~',
                '',
                trim($branch, "* \t\n\r\0\x0B")
            );

            return BranchName::fromName($sanitizedBranch);
        }, $branches));
    };

    $createTag = static function (
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId
    ) : void {
        $tagFileName = tempnam(sys_get_temp_dir(), 'created_tag');

        file_put_contents($tagFileName, $changelog);

        (new Process(['git', 'checkout', $sourceBranch->name()], $repositoryDirectory))
            ->mustRun();

        (new Process(
            ['git', 'tag', $tagName, '-F', $tagFileName, '--cleanup=verbatim', '--local-user=' . $keyId->id()],
            $repositoryDirectory
        ))
            ->mustRun();
    };

    $push = static function (
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ) : void {
        if ($alias === null) {
            (new Process(['git', 'push', 'origin', $symbol], $repositoryDirectory))
                ->mustRun();

            return;
        }

        $localTemporaryBranch = uniqid('temporary-branch', true);

        (new Process(['git', 'branch', $localTemporaryBranch, $symbol], $repositoryDirectory))
            ->mustRun();

        (new Process(['git', 'push', 'origin', $localTemporaryBranch . ':' . $alias], $repositoryDirectory))
            ->mustRun();
    };

    $importGpgKey = static function (string $keyContents) : SecretKeyId {
        $keyFileName = tempnam(sys_get_temp_dir(), 'imported-key');

        file_put_contents($keyFileName, $keyContents);

        $output = (new Process(['gpg', '--import', $keyFileName]))
            ->mustRun()
            ->getErrorOutput();

        Assert::that($output)
              ->regex('/key\\s+([A-F0-9]+):\\s+secret\\s+key\\s+imported/im');

        preg_match('/key\\s+([A-F0-9]+):\\s+secret\\s+key\\s+imported/im', $output, $matches);

        assert(is_array($matches));

        return SecretKeyId::fromBase16String($matches[1]);
    };

    $environment = Variables::fromEnvironment();

    $milestone = (new LoadCurrentGithubEventFromGithubActionPath($environment))
        ->__invoke();

    Assert::that($milestone)
        ->isInstanceOf(MilestoneClosedEvent::class, 'Provided github event is not of type "milestone closed"');

    $repositoryName = $milestone->repository();

    if (class_exists(Profiler::class, false)) {
        Profiler::setCustomVariable('repository', $repositoryName->owner() . '/' . $repositoryName->name());
        Profiler::setCustomVariable('version', $milestone->version()->fullReleaseName());
    }

    $repositoryName->assertMatchesOwner($environment->githubOrganisation()); // @TODO limit via ENV?

    $repository                  = $repositoryName->uriWithTokenAuthentication($environment->githubToken());
    $releasedRepositoryLocalPath = $buildDir . '/' . $repositoryName->name();

    $importedKey = $importGpgKey($environment->signingSecretKey());

    $cleanBuildDir();
    $cloneRepository(
        $repository,
        $releasedRepositoryLocalPath,
        $environment->gitAuthorName(),
        $environment->gitAuthorEmail()
    );

    $candidates = $getBranches($releasedRepositoryLocalPath);

    $releaseVersion = $milestone->version();

    $milestoneChangelog = (new GetMilestoneChangelog(new RunGraphQLQuery(
        Psr17FactoryDiscovery::findRequestFactory(),
        HttpClientDiscovery::find(),
        $environment->githubToken()
    )))->__invoke(
        $repositoryName,
        $milestone->milestoneNumber()
    );

    $milestoneChangelog->assertAllIssuesAreClosed();

    $releaseBranch = $candidates->targetBranchFor($milestone->version());

    if ($releaseBranch === null) {
        throw new RuntimeException(sprintf(
            'No valid release branch found for version %s',
            $milestone->version()->fullReleaseName()
        ));
    }

    $changelog = (new CreateChangelogText(JwageGenerateChangelog::create(
        Psr17FactoryDiscovery::findRequestFactory(),
        HttpClientDiscovery::find(),
    )))
        ->__invoke(
            $milestoneChangelog,
            $milestone->repository(),
            $milestone->version()
        );

    $tagName = $releaseVersion->fullReleaseName();

    $createTag(
        $releasedRepositoryLocalPath,
        $releaseBranch,
        $releaseVersion->fullReleaseName(),
        $changelog,
        $importedKey
    );

    $push($releasedRepositoryLocalPath, $tagName);
    $push($releasedRepositoryLocalPath, $tagName, $releaseVersion->targetReleaseBranchName()->name());

    $mergeUpTarget = $candidates->branchToMergeUp($milestone->version());

    $releaseUrl = (new CreateRelease(
        Psr17FactoryDiscovery::findRequestFactory(),
        HttpClientDiscovery::find(),
        $environment->githubToken()
    ))->__invoke(
        $repositoryName,
        $releaseVersion,
        $changelog
    );

    if ($mergeUpTarget !== null) {
        $mergeUpBranch = BranchName::fromName(
            $releaseBranch->name()
            . '-merge-up-into-'
            . $mergeUpTarget->name()
            . uniqid('_', true) // This is to ensure that a new merge-up pull request is created even if one already exists
        );
        $push($releasedRepositoryLocalPath, $releaseBranch->name(), $mergeUpBranch->name());

        (new CreatePullRequest(
            Psr17FactoryDiscovery::findRequestFactory(),
            HttpClientDiscovery::find(),
            $environment->githubToken()
        ))->__invoke(
            $repositoryName,
            $mergeUpBranch,
            $mergeUpTarget,
            'Merge release ' . $tagName . ' into ' . $mergeUpTarget->name(),
            $changelog
        );
    }

    echo 'Released: ' . $releaseUrl->__toString();
})();
