<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psl;
use Psl\Json;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class CreatePullRequestThroughApiCall implements CreatePullRequest
{
    private const API_ROOT = 'https://api.github.com/';

    private RequestFactoryInterface $messageFactory;

    private ClientInterface $client;

    private string $apiToken;

    /** @psalm-param non-empty-string $apiToken */
    public function __construct(
        RequestFactoryInterface $messageFactory,
        ClientInterface $client,
        string $apiToken
    ) {
        $this->messageFactory = $messageFactory;
        $this->client         = $client;
        $this->apiToken       = $apiToken;
    }

    public function __invoke(
        RepositoryName $repository,
        BranchName $head,
        BranchName $target,
        string $title,
        string $body
    ): void {
        $request = $this->messageFactory
            ->createRequest(
                'POST',
                self::API_ROOT . 'repos/' . $repository->owner() . '/' . $repository->name() . '/pulls'
            )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal API V3 client')
            ->withAddedHeader('Authorization', 'token ' . $this->apiToken);

        $request
            ->getBody()
            ->write(Json\encode([
                'title'                 => $title,
                'head'                  => $head->name(),
                'base'                  => $target->name(),
                'body'                  => $body,
                'maintainer_can_modify' => true,
                'draft'                 => false,
            ]));

        $response = $this->client->sendRequest($request);

        Psl\invariant($response->getStatusCode() >= 200 && $response->getStatusCode() <= 299, 'Failed to create pull request through GitHub API.');

        $responseBody = $response
            ->getBody()
            ->__toString();

        Json\typed($responseBody, Type\shape([
            'url' => Type\mixed(),
        ]));
    }
}
