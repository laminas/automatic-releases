<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Monolog;

use DateTimeImmutable;
use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\HttpClient\LoggingHttpClient;
use Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpRequestsIntoStrings;
use Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpResponsesIntoStrings;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use function fopen;

/** @covers \Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpResponsesIntoStrings */
final class ConvertLogContextHttpResponsesIntoStringsTest extends TestCase
{
    public function testWillScrubSensitiveRequestInformation(): void
    {
        $date = new DateTimeImmutable();

        $requestFactory = Psr17FactoryDiscovery::findResponseFactory();

        $plainResponse = $requestFactory->createResponse(401);

        $sensitiveResponse = $requestFactory->createResponse(403)
            ->withAddedHeader('Super', 'secret');

        $sensitiveResponse->getBody()
            ->write('this should be printed');

        self::assertEquals(
            new LogRecord(
                $date,
                'a-channel',
                Level::Critical,
                'a message',
                [
                    'foo'               => 'bar',
                    'plain response'     => '401 ""',
                    'sensitive response' => '403 "this should be printed"',
                ]
            ),
            (new ConvertLogContextHttpResponsesIntoStrings())(new LogRecord(
                $date,
                'a-channel',
                Level::Critical,
                'a message',
                [
                    'foo'               => 'bar',
                    'plain response'     => $plainResponse,
                    'sensitive response' => $sensitiveResponse,
                ]
            ))
        );
    }
}
