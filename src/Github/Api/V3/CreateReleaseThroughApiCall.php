<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Uri;
use Psl;
use Psl\Json;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;

final class CreateReleaseThroughApiCall implements CreateRelease
{
    private const API_ROOT = 'https://api.github.com/';

    private RequestFactoryInterface $messageFactory;

    private ClientInterface $client;

    /** @psalm-var non-empty-string */
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
        SemVerVersion $version,
        string $releaseNotes
    ): UriInterface {
        $request = $this->messageFactory
            ->createRequest(
                'POST',
                self::API_ROOT . 'repos/' . $repository->owner() . '/' . $repository->name() . '/releases'
            )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal API V3 client')
            ->withAddedHeader('Authorization', 'token ' . $this->apiToken);

        $request
            ->getBody()
            ->write(Json\encode([
                'tag_name' => $version->fullReleaseName(),
                'name'     => $version->fullReleaseName(),
                'body'     => $releaseNotes,
            ]));

        $response = $this->client->sendRequest($request);

        Psl\invariant($response->getStatusCode() >= 200 || $response->getStatusCode() <= 299, 'Failed to create release through GitHub API.');

        $responseBody = $response
            ->getBody()
            ->__toString();

        $responseData = Json\typed($responseBody, Type\shape([
            'html_url' => Type\non_empty_string(),
        ]));

        return new Uri($responseData['html_url']);
    }
}
