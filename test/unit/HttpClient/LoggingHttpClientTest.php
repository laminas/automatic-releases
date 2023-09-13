<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\HttpClient;

use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\HttpClient\LoggingHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

use function in_array;

/** @covers \Laminas\AutomaticReleases\HttpClient\LoggingHttpClient */
final class LoggingHttpClientTest extends TestCase
{
    private int $callCount = 0;

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

        $this->callCount = 0;

        $logger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                self::callback(static function (string $message): bool {
                    self::assertTrue(in_array($message, [
                        'Sending request {request}',
                        'Received response {response} to request {request}',
                    ], true));

                    return true;
                }),
                self::callback(function (array $params) use ($request, $response): bool {
                    $this->callCount++;
                    self::assertArrayHasKey('request', $params);
                    self::assertSame($request, $params['request']);

                    if ($this->callCount === 1) {
                        return true;
                    }

                    self::assertArrayHasKey('response', $params);
                    self::assertSame($response, $params['response']);

                    return true;
                }),
            );

        self::assertSame(
            $response,
            (new LoggingHttpClient($next, $logger))
                ->sendRequest($request),
        );
    }
}
