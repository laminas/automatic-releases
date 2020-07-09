<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL\Query;

use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\Api\GraphQL\RunQuery;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Webmozart\Assert\Assert;

final class GetMilestoneFirst100IssuesAndPullRequests implements GetGithubMilestone
{
    // @TODO this fetches ONLY the first 100 issues!!!
    private const QUERY = <<<'GRAPHQL'
query GetStuff($owner: String!, $repositoryName: String!, $milestoneNumber: Int!) {
  repository(name: $repositoryName, owner: $owner) {
    milestone (number: $milestoneNumber) {
      url,
      number,
      closed,
      title,
      description,
      url,
      issues (first: 100) {
        nodes {
          number,
          title,
          closed,
          url,
          author {
            login,
            url
          },
          labels (first: 100) {
            nodes {
              color,
              name,
              url
            }
          }
        }
      },
      pullRequests (first: 100) {
        nodes {
          number,
          title,
          merged,
          closed,
          url,
          author {
            login,
            url
          },
          labels (first: 100) {
            nodes {
              color,
              name,
              url
            }
          }
        }
      }
    }
  }
}
GRAPHQL;

    private RunQuery $runQuery;

    public function __construct(RunQuery $runQuery)
    {
        $this->runQuery = $runQuery;
    }

    public function __invoke(
        RepositoryName $repositoryName,
        int $milestoneNumber
    ): Milestone {
        $queryResult = $this->runQuery->__invoke(
            self::QUERY,
            [
                'repositoryName'  => $repositoryName->name(),
                'owner'           => $repositoryName->owner(),
                'milestoneNumber' => $milestoneNumber,
            ]
        );

        Assert::keyExists($queryResult, 'repository');
        Assert::isMap($queryResult['repository']);
        Assert::keyExists($queryResult['repository'], 'milestone');
        Assert::isMap($queryResult['repository']['milestone']);

        return Milestone::fromPayload($queryResult['repository']['milestone']);
    }
}
