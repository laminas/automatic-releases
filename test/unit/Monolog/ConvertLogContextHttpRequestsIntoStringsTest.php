<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Monolog;

use DateTimeImmutable;
use Http\Discovery\Psr17FactoryDiscovery;
use Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpRequestsIntoStrings;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/** @covers \Laminas\AutomaticReleases\Monolog\ConvertLogContextHttpRequestsIntoStrings */
final class ConvertLogContextHttpRequestsIntoStringsTest extends TestCase
{
    public function testWillScrubSensitiveRequestInformation(): void
    {
        $date = new DateTimeImmutable();

        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

        $plainRequest = $requestFactory->createRequest('GET', 'http://example.com/foo');

        $sensitiveRequest = $requestFactory->createRequest('POST', 'https://user:pass@example.com/foo?bar=baz')
            ->withAddedHeader('Authentication', ['also secret']);

        $sensitiveRequest->getBody()
            ->write('super: secret');

        self::assertEquals(
            new LogRecord(
                $date,
                'a-channel',
                Level::Critical,
                'a message',
                [
                    'foo'               => 'bar',
                    'plain request'     => 'GET http://example.com/foo',
                    'sensitive request' => 'POST https://example.com/foo?bar=baz',
                ],
            ),
            (new ConvertLogContextHttpRequestsIntoStrings())(new LogRecord(
                $date,
                'a-channel',
                Level::Critical,
                'a message',
                [
                    'foo'               => 'bar',
                    'plain request'     => $plainRequest,
                    'sensitive request' => $sensitiveRequest,
                ],
            )),
        );
    }
}
