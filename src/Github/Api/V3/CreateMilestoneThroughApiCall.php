<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Webmozart\Assert\Assert;

use function Safe\json_decode;
use function Safe\json_encode;
use function sprintf;

final class CreateMilestoneThroughApiCall implements CreateMilestone
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
        SemVerVersion $version
    ): UriInterface {
        $request = $this->messageFactory
            ->createRequest(
                'POST',
                self::API_ROOT . 'repos/' . $repository->owner() . '/' . $repository->name() . '/milestones'
            )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal API V3 client')
            ->withAddedHeader('Authorization', 'bearer ' . $this->apiToken);

        $request
            ->getBody()
            ->write(json_encode([
                'tag_name' => $version->fullReleaseName(),
                'name'     => $version->fullReleaseName(),
                'body'     => sprintf('{"title":"%s"}', $version->fullReleaseName()),
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        Assert::greaterThanEq($response->getStatusCode(), 200);
        Assert::lessThanEq($response->getStatusCode(), 299);

        $responseData = json_decode($responseBody, true);

        Assert::isMap($responseData);
        Assert::keyExists($responseData, 'html_url');
        Assert::stringNotEmpty($responseData['html_url']);

        return new Uri($responseData['html_url']);
    }
}
