<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
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

    private LoggerInterface $logger;

    /** @psalm-param non-empty-string $apiToken */
    public function __construct(
        RequestFactoryInterface $messageFactory,
        ClientInterface $client,
        string $apiToken,
        LoggerInterface $logger
    ) {
        $this->messageFactory = $messageFactory;
        $this->client         = $client;
        $this->apiToken       = $apiToken;
        $this->logger         = $logger;
    }

    public function __invoke(
        RepositoryName $repository,
        SemVerVersion $version
    ): UriInterface {
        $this->logger->info(sprintf(
            'Creating milestone "%s" for "%s/%s"',
            $version->fullReleaseName(),
            $repository->owner(),
            $repository->name()
        ));

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
                'title' => $version->fullReleaseName()
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        if ($response->getStatusCode() !== 201) {
            throw CreateMilestoneFailed::forVersion($version->fullReleaseName(), $responseBody);
        }

        Assert::eq($response->getStatusCode(), 201);

        $responseData = json_decode($responseBody, true);
        Assert::isMap($responseData);
        Assert::keyExists($responseData, 'html_url');
        Assert::stringNotEmpty($responseData['html_url']);

        $this->logger->info(sprintf(
            'Milestone "%s" created',
            $version->fullReleaseName()
        ));

        return new Uri($responseData['html_url']);
    }
}
