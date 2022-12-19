<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;

use function Psl\invariant_violation;
use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Type\non_empty_string;
use function Psl\Type\shape;
use function sprintf;

final class CreateReleaseThroughApiCall implements CreateRelease
{
    private const API_URI = 'https://api.github.com/repos/%s/%s/releases';

    /** @psalm-param non-empty-string $apiToken */
    public function __construct(
        private readonly RequestFactoryInterface $messageFactory,
        private readonly ClientInterface $client,
        private readonly string $apiToken,
    ) {
    }

    public function __invoke(
        RepositoryName $repository,
        SemVerVersion $version,
        string $releaseNotes,
    ): UriInterface {
        $request = $this->messageFactory
            ->createRequest(
                'POST',
                sprintf(self::API_URI, $repository->owner(), $repository->name()),
            )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal API V3 client')
            ->withAddedHeader('Authorization', 'token ' . $this->apiToken);

        $request->getBody()->write(encode([
            'tag_name' => $version->fullReleaseName(),
            'name'     => $version->fullReleaseName(),
            'body'     => $releaseNotes,
        ]));

        $response = $this->client->sendRequest($request);

        $statusCode        = $response->getStatusCode();
        $responseBody      = (string) $response->getBody();
        $validResponseCode = $statusCode >= 200 && $statusCode <= 299;

        if ($validResponseCode) {
            $responseData = typed($responseBody, shape([
                'html_url' => non_empty_string(),
            ]));

            return new Uri($responseData['html_url']);
        }

        $errorResponseData = typed($responseBody, shape([
            'message' => non_empty_string(),
        ]));

        invariant_violation(
            "Failed to create release through GitHub API;\nStatus code: %s\nMessage: %s\n",
            $statusCode,
            $errorResponseData['message'],
        );
    }
}
