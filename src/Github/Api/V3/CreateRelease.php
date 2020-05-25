<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\V3;

use Assert\Assert;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;
use function Safe\json_decode;
use function Safe\json_encode;

final class CreateRelease
{
    private const API_ROOT = 'https://api.github.com/';

    /** @var RequestFactoryInterface */
    private $messageFactory;

    /** @var ClientInterface */
    private $client;

    /** @var string */
    private $apiToken;

    public function __construct(
        RequestFactoryInterface $messageFactory,
        ClientInterface $client,
        string $apiToken
    ) {
        Assert::that($apiToken)
            ->notEmpty();

        $this->messageFactory = $messageFactory;
        $this->client         = $client;
        $this->apiToken       = $apiToken;
    }

    public function __invoke(
        RepositoryName $repository,
        SemVerVersion $version,
        string $releaseNotes
    ) : UriInterface {
        Assert::that($releaseNotes)
              ->notEmpty();

        $request = $this->messageFactory
            ->createRequest(
                'POST',
                self::API_ROOT . 'repos/' . $repository->owner() . '/' . $repository->name() . '/releases'
            )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal API V3 client')
            ->withAddedHeader('Authorization', 'bearer ' . $this->apiToken);

        $request
            ->getBody()
            ->write(json_encode([
                'tag_name' => $version->fullReleaseName(),
                'name'     => $version->fullReleaseName(),
                'body'     => $releaseNotes,
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        Assert::that($response->getStatusCode())
            ->between(200, 299, $responseBody);

        Assert::that($responseBody)
              ->isJsonString();

        $responseData = json_decode($responseBody, true);

        Assert::that($responseData)
              ->keyExists('html_url', $responseBody);

        return new Uri($responseData['html_url']);
    }
}
