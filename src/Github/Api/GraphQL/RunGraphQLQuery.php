<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\GraphQL;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Webmozart\Assert\Assert;
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

    /** {@inheritDoc} */
    public function __invoke(
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

        Assert::same($response->getStatusCode(), 200);

        $responseData = json_decode($responseBody, true);

        Assert::isMap($responseData);
        Assert::keyNotExists($responseData, 'errors');
        Assert::keyExists($responseData, 'data');
        Assert::isArray($responseData['data']);

        return $responseData['data'];
    }
}
