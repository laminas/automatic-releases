<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/** @internal */
final class LoggingHttpClient implements ClientInterface
{
    public function __construct(private readonly ClientInterface $next, private readonly LoggerInterface $logger)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->logger->debug('Sending request {request}', ['request' => $request]);

        $response = $this->next->sendRequest($request);

        $this->logger->debug(
            'Received response {response} to request {request}',
            [
                'request'  => $request,
                'response' => $response,
            ],
        );

        return $response;
    }
}
