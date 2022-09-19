<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\HttpClient;

use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\HttpClient\LoggingHttpClient;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use function fopen;

/** @covers \Laminas\AutomaticReleases\HttpClient\LoggingHttpClient */
final class LoggingHttpClientTest extends TestCase
{
    public function testWillLogRequestAndResponse(): void
    {
        $request  = Psr17FactoryDiscovery::findRequestFactory()->createRequest('get', 'http://example.com/foo/bar');
        $response = Psr17FactoryDiscovery::findResponseFactory()->createResponse(204);

        $response->getBody()
            ->write('hello world');

        $logger = $this->createMock(LoggerInterface::class);
        $next   = $this->createMock(ClientInterface::class);

        $next->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $logger->expects(self::exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Sending request {request}', ['request' => $request]],
                [
                    'Received response {response} to request {request}',
                    [
                        'request'  => $request,
                        'response' => $response,
                    ],
                ],
            );

        self::assertSame(
            $response,
            (new LoggingHttpClient($next, $logger))
                ->sendRequest($request)
        );
    }
}
