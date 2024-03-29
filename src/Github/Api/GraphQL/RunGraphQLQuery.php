<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\GraphQL;

use Psl;
use Psl\Json;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

use function array_key_exists;

final readonly class RunGraphQLQuery implements RunQuery
{
    private const ENDPOINT = 'https://api.github.com/graphql';

    /** @psalm-param non-empty-string $apiToken */
    public function __construct(
        private readonly RequestFactoryInterface $messageFactory,
        private readonly ClientInterface $client,
        private readonly string $apiToken,
    ) {
    }

    /** {@inheritDoc} */
    public function __invoke(
        string $query,
        array $variables = [],
    ): array {
        $request = $this->messageFactory
            ->createRequest('POST', self::ENDPOINT)
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('User-Agent', 'Ocramius\'s minimal GraphQL client - stolen from Dunglas')
            ->withAddedHeader('Authorization', 'bearer ' . $this->apiToken);

        $request
            ->getBody()
            ->write(Json\encode([
                'query'     => $query,
                'variables' => $variables,
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        Type\literal_scalar(200)->assert($response->getStatusCode());

        $response = Json\typed($responseBody, Type\shape([
            'data'   => Type\dict(Type\string(), Type\mixed()),
            'errors' => Type\optional(Type\mixed()),
        ]));

        Psl\invariant(! array_key_exists('errors', $response), 'GraphQL query execution failed');

        return $response['data'];
    }
}
