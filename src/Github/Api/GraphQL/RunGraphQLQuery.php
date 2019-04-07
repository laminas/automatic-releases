<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL;

use Assert\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use function Safe\json_decode;
use function Safe\json_encode;

final class RunGraphQLQuery implements RunQuery
{
    private const ENDPOINT = 'https://api.github.com/graphql';

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

    function __invoke(
        string $query,
        array $variables = []
    ) : array {
        $request = $this->messageFactory
            ->createRequest('POST', self::ENDPOINT)
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal GraphQL client - stolen from Dunglas')
            ->withAddedHeader('Authorization', 'bearer ' . $this->apiToken);

        $request
            ->getBody()
            ->write(json_encode([
                'query'     => $query,
                'variables' => $variables,
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        Assert::that($response->getStatusCode())
              ->same(200, $responseBody);

        Assert::that($responseBody)
              ->isJsonString();

        $responseData = json_decode($responseBody, true);

        Assert::that($responseData)
              ->keyNotExists('errors', $responseBody)
              ->keyExists('data', $responseBody);

        Assert::that($responseData['data'])
              ->isArray($responseBody);

        return $responseData['data'];
    }
}
