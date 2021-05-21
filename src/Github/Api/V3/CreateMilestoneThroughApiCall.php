<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Psl\Json;
use Psl\Str;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

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

    public function __invoke(RepositoryName $repository, SemVerVersion $version): void
    {
        $this->logger->info(Str\format(
            '[CreateMilestoneThroughApiCall] Creating milestone "%s" for "%s/%s"',
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
            ->withAddedHeader('Authorization', 'token ' . $this->apiToken);

        $request
            ->getBody()
            ->write(Json\encode([
                'title' => $version->fullReleaseName(),
                'description' => $this->milestoneDescription($version),
            ]));

        $response = $this->client->sendRequest($request);

        $responseBody = $response
            ->getBody()
            ->__toString();

        $responseData = Json\typed($responseBody, Type\dict(Type\string(), Type\mixed()));

        if ($response->getStatusCode() !== 201) {
            $this->logger->error(
                Str\format(
                    '[CreateMilestoneThroughApiCall] Failed to create milestone "%s"',
                    $version->fullReleaseName()
                ),
                ['exception' => $responseData]
            );

            throw CreateMilestoneFailed::forVersion($version->fullReleaseName());
        }

        Type\literal_scalar(201)->assert($response->getStatusCode());

        $this->logger->info(Str\format(
            '[CreateMilestoneThroughApiCall] Milestone "%s" created',
            $version->fullReleaseName()
        ));
    }

    private function milestoneDescription(SemVerVersion $version): string
    {
        if ($version->isNewMajorRelease()) {
            return 'Backwards incompatible release (major)';
        }

        if ($version->isNewMinorRelease()) {
            return 'Feature release (minor)';
        }

        return Str\format('%s bugfix release (patch)', $version->targetReleaseBranchName()->name());
    }
}
