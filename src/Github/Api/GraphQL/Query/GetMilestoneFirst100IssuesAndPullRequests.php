<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL\Query;

use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunQuery;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;

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

    /** @var RunQuery */
    private $runQuery;

    public function __construct(RunQuery $runQuery)
    {
        $this->runQuery = $runQuery;
    }

    public function __invoke(
        RepositoryName $repositoryName,
        int $milestoneNumber
    ) : Milestone {
        return Milestone::fromPayload($this->runQuery->__invoke(
            self::QUERY,
            [
                'repositoryName'  => $repositoryName->name(),
                'owner'           => $repositoryName->owner(),
                'milestoneNumber' => $milestoneNumber,
            ]
        )['repository']['milestone']);
    }
}
