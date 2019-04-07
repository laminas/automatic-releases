<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\WebApplication;

use Assert\Assert;
use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Doctrine\AutomaticReleases\Github\Api\Hook\VerifyRequestSignature;
use Doctrine\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateRelease;
use Doctrine\AutomaticReleases\Github\CreateChangelogText;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use ErrorException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Zend\Diactoros\ServerRequestFactory;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use function array_map;
use function assert;
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

(static function () : void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function ($errorCode, $message = '', $file = '', $line = 0) : void {
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

    $cloneRepository = static function (UriInterface $repositoryUri, string $targetPath) : void {
        (new Process(['git', 'clone', $repositoryUri->__toString(), $targetPath]))
            ->mustRun();
    };

    $getBranches = static function (string $repositoryDirectory) : MergeTargetCandidateBranches {
        (new Process(['git', 'fetch'], $repositoryDirectory))
            ->mustRun();

        $branches = explode(
            "\n",
            (new Process(['git', 'branch', '-r'], $repositoryDirectory))
                ->mustRun()
                ->getOutput()
        );

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
        $pushedRef = $alias !== null ? $symbol . ':' . $alias : $symbol;

        (new Process(['git', 'push', 'origin', $pushedRef], $repositoryDirectory))
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

    $request = ServerRequestFactory::fromGlobals();

    $environment = Variables::fromEnvironment();

    (new VerifyRequestSignature())->__invoke($request, $environment->githubHookSecret());

    if (! MilestoneClosedEvent::appliesToRequest($request)) {
        echo 'Event does not apply.';

        return;
    }

    $postData = $request->getParsedBody();

    Assert::that($postData)
          ->isArray()
          ->keyExists('payload');

    $milestone      = MilestoneClosedEvent::fromEventJson($_POST['payload']);
    $repositoryName = $milestone->repository();

    $repositoryName->assertMatchesOwner($environment->githubOrganisation()); // @TODO limit via ENV?

    $repository                  = $repositoryName->uriWithTokenAuthentication($environment->githubToken());
    $releasedRepositoryLocalPath = $buildDir . '/' . $repositoryName->name();

    $importedKey = $importGpgKey($environment->signingSecretKey());

    $cleanBuildDir();
    $cloneRepository($repository, $releasedRepositoryLocalPath);

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

    $changelog = (new CreateChangelogText())->__invoke($milestoneChangelog);

    $tagName = $releaseVersion->fullReleaseName();

    $createTag(
        $releasedRepositoryLocalPath,
        $releaseBranch,
        $releaseVersion->fullReleaseName(),
        $changelog,
        $importedKey
    );

    $mergeUpTarget = $candidates->branchToMergeUp($milestone->version());
    $mergeUpBranch = BranchName::fromName(
        $releaseBranch->name()
        . '-merge-up-into-'
        . $mergeUpTarget->name()
        . uniqid('_', true) // This is to ensure that a new merge-up pull request is created even if one already exists
    );

    $push($releasedRepositoryLocalPath, $tagName);
    $push($releasedRepositoryLocalPath, $releaseBranch->name(), $mergeUpBranch->name());

    $releaseUrl = (new CreateRelease(
        Psr17FactoryDiscovery::findRequestFactory(),
        HttpClientDiscovery::find(),
        $environment->githubToken()
    ))->__invoke(
        $repositoryName,
        $releaseVersion,
        $changelog
    );

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

    echo 'Released: ' . $releaseUrl->__toString();
})();
