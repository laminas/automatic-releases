<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github;

use ChangelogGenerator\ChangelogConfig;
use ChangelogGenerator\ChangelogGenerator;
use ChangelogGenerator\GitHubCredentials;
use ChangelogGenerator\IssueClient;
use ChangelogGenerator\IssueFactory;
use ChangelogGenerator\IssueFetcher;
use ChangelogGenerator\IssueGrouper;
use ChangelogGenerator\IssueRepository;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class JwageGenerateChangelog implements GenerateChangelog
{
    private ChangelogGenerator $changelogGenerator;
    private GitHubCredentials $gitHubCredentials;

    public function __construct(ChangelogGenerator $changelogGenerator, GitHubCredentials $gitHubCredentials)
    {
        $this->changelogGenerator = $changelogGenerator;
        $this->gitHubCredentials  = $gitHubCredentials;
    }

    public static function create(
        RequestFactoryInterface $messageFactory,
        ClientInterface $client,
        GitHubCredentials $gitHubCredentials
    ): self {
        $issueClient     = new IssueClient($messageFactory, $client);
        $issueFactory    = new IssueFactory();
        $issueFetcher    = new IssueFetcher($issueClient);
        $issueRepository = new IssueRepository($issueFetcher, $issueFactory);
        $issueGrouper    = new IssueGrouper();

        return new self(new ChangelogGenerator($issueRepository, $issueGrouper), $gitHubCredentials);
    }

    public function __invoke(
        RepositoryName $repositoryName,
        SemVerVersion $semVerVersion
    ): string {
        $config = (new ChangelogConfig())
            ->setUser($repositoryName->owner())
            ->setRepository($repositoryName->name())
            ->setMilestone($semVerVersion->fullReleaseName())
            ->setGitHubCredentials($this->gitHubCredentials);

        $output = new BufferedOutput();

        $this->changelogGenerator->generate(
            $config,
            $output
        );

        return $output->fetch();
    }
}
