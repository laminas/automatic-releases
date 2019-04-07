<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\Hook;

use Assert\AssertionFailedException;
use Doctrine\AutomaticReleases\Github\Api\Hook\VerifyRequestSignature;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

final class VerifyRequestSignatureTest extends TestCase
{
    /** @dataProvider validSignatures */
    public function testValidSignature(string $secret, string $body, string $signatureHeader) : void
    {
        $request = $this->createMock(RequestInterface::class);
        $stream  = $this->createMock(StreamInterface::class);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Hub-Signature')
            ->willReturn($signatureHeader);

        $stream
            ->expects(self::any())
            ->method('__toString')
            ->willReturn($body);

        $request
            ->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        (new VerifyRequestSignature())
            ->__invoke($request, $secret);

        // assertion is silent if it doesn't fail
        $this->addToAssertionCount(1);
    }

    public function validSignatures() : array
    {
        return [
            ['the_secret', 'the_body', 'sha1=578824bd817c673685995ff825fe9efb38caf1f5'],
            ['another_secret', 'the_body', 'sha1=dd19ebefd33527d1011205cf1eb87ae7306e2f03'],
        ];
    }

    /** @dataProvider invalidSignatures */
    public function testInvalidSignature(string $secret, string $body, string $signatureHeader) : void
    {
        $request = $this->createMock(RequestInterface::class);
        $stream  = $this->createMock(StreamInterface::class);

        $request
            ->expects(self::any())
            ->method('getHeaderLine')
            ->with('X-Hub-Signature')
            ->willReturn($signatureHeader);

        $stream
            ->expects(self::any())
            ->method('__toString')
            ->willReturn($body);

        $request
            ->expects(self::any())
            ->method('getBody')
            ->willReturn($stream);

        $validator = new VerifyRequestSignature();

        $this->expectException(AssertionFailedException::class);

        $validator->__invoke($request, $secret);
    }

    public function invalidSignatures() : array
    {
        return [
            ['the_secret', 'the_body', 'sha1=578824bd817c673685995ff825fe9efb38caf1f6'],
            ['another_secret', 'the_body', 'sha1=578824bd817c673685995ff825fe9efb38caf1f6'],
        ];
    }
}
