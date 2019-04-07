<?php

declare(strict_types=1);

namespace Roave\SecurityAdvisories;

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
use UnexpectedValueException;
use Zend\Diactoros\ServerRequestFactory;
use function escapeshellarg;
use function exec;
use function implode;
use function Safe\chdir;
use function Safe\file_put_contents;
use function Safe\getcwd;
use function Safe\sprintf;
use function set_error_handler;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use const PHP_EOL;

(static function () : void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function ($errorCode, $message = '', $file = '', $line = 0) : void {
            throw new ErrorException($message, 0, $errorCode, $file, $line);
        },
        E_STRICT | E_NOTICE | E_WARNING
    );

    $buildDir = __DIR__ . '/../build';

    $runInPath = static function (callable $function, string $path) {
        $originalPath = getcwd();

        chdir($path);

        try {
            $returnValue = $function();
        } finally {
            chdir($originalPath);
        }

        return $returnValue;
    };

    $execute = static function (string $commandString) : array {
        // may the gods forgive me for this in-lined command addendum, but I CBA to
        // fix proc_open's handling of exit codes.
        exec($commandString . ' 2>&1', $output, $result);

        if ($result !== 0) {
            throw new UnexpectedValueException(sprintf(
                'Command failed: "%s" "%s"',
                $commandString,
                implode(PHP_EOL, $output)
            ));
        }

        return $output;
    };

    $cleanBuildDir = static function () use ($buildDir, $execute) : void {
        $execute('rm -rf ' . escapeshellarg($buildDir));
        $execute('mkdir ' . escapeshellarg($buildDir));
    };

    $cloneRepository = static function (UriInterface $repositoryUri, string $targetPath) use ($execute) : void {
        $execute(
            'git clone '
            . escapeshellarg($repositoryUri->__toString())
            . ' ' . escapeshellarg($targetPath)
        );
    };

    $getBranches = static function (string $repositoryDirectory) use (
        $runInPath,
        $execute
    ) : MergeTargetCandidateBranches {
        return $runInPath(static function () use ($execute) {
            $execute('git fetch');

            return MergeTargetCandidateBranches::fromAllBranches(...array_map(function (string $branch) : BranchName {
                return BranchName::fromName(\Safe\preg_replace(
                    '/^(?:remotes\\/)?origin\\//',
                    '',
                    trim($branch, "* \t\n\r\0\x0B")
                ));
            }, $execute('git branch -r')));
        }, $repositoryDirectory);
    };

    $createTag = static function (
        string $repositoryDirectory, BranchName $sourceBranch, string $tagName, string $changelog, SecretKeyId $keyId
    ) use (
        $runInPath,
        $execute
    ) : void {
        $tagFileName = \Safe\tempnam(sys_get_temp_dir(), 'created_tag');

        file_put_contents($tagFileName, $changelog);

        $runInPath(static function () use ($sourceBranch, $tagName, $keyId, $tagFileName, $execute) {
            $execute(sprintf('git checkout "%s"', $sourceBranch->name()));
            $execute(sprintf(
                'git tag %s -F %s --cleanup=verbatim --local-user=%s',
                escapeshellarg($tagName),
                escapeshellarg($tagFileName),
                escapeshellarg($keyId->id())
            ));
        }, $repositoryDirectory);
    };

    $push = static function (string $repositoryDirectory, string $symbol, ?string $alias = null) use (
        $runInPath,
        $execute
    ) : void {
        $runInPath(static function () use ($symbol, $alias, $execute) {
            $execute(sprintf(
                'git push origin %s',
                $alias !== null ? escapeshellarg($symbol) . ':' . escapeshellarg($alias) : escapeshellarg($symbol)
            ));
        }, $repositoryDirectory);
    };

    $importGpgKey = static function (string $keyContents) use ($execute) : SecretKeyId {
        $keyFileName = \Safe\tempnam(sys_get_temp_dir(), 'imported-key');

        file_put_contents($keyFileName, $keyContents);

        $output = $execute(sprintf('gpg --import %s 2>&1 | grep "secret key imported"', escapeshellarg($keyFileName)));

        Assert::that($output)
              ->keyExists(0);

        Assert::that($output[0])
              ->regex('/key\\s+([A-F0-9]+):\s+/i');

        \Safe\preg_match('/key\\s+([A-F0-9]+):\s+/i', $output[0], $matches);

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

    Assert::that($releaseBranch)
          ->notNull();

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
        . \uniqid('_', true) // This is to ensure that a new merge-up pull request is created even if one already exists
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
