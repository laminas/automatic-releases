<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Monolog;

use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpRequestsIntoStrings;
use Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpResponsesIntoStrings;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use function fopen;

/**
 * Small integration test to ensure future compatibility with monolog in our setup.
 *
 * @coversNothing
 */
final class VerifyLoggingIntegrationTest extends TestCase
{
    public function testLoggedRequestAndResponseBodyPartsDoNotContainSecretsAndPostData(): void
    {
        $request  = Psr17FactoryDiscovery::findRequestFactory()->createRequest('get', 'http://example.com/foo/bar');
        $response = Psr17FactoryDiscovery::findResponseFactory()->createResponse(204);

        $response->getBody()
            ->write('hello world');

        $stream = fopen('php://memory', 'rwb+');

        $bufferHandler = new StreamHandler($stream);

        $logger = new Logger(
            'test-logger',
            [$bufferHandler],
            [
                new ConvertLogContextHttpRequestsIntoStrings(),
                new ConvertLogContextHttpResponsesIntoStrings(),
            ]
        );

        $logger->debug('message', ['request' => $request, 'response' => $response]);

        rewind($stream);

        self::assertStringContainsString(
            ': message {"request":"GET http://example.com/foo/bar","response":"204 \"hello world\""} []',
            stream_get_contents($stream),
            'Request and response contents have been serialized into the final log message'
        );
    }
}
