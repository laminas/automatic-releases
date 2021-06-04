<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psl\Json\Exception\DecodeException;
use Psl\SecureRandom;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall */
final class SetDefaultBranchThroughApiCallTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    /** @var RequestFactoryInterface&MockObject */
    private RequestFactoryInterface $messageFactory;
    /** @psalm-var non-empty-string */
    private string $apiToken;
    private SetDefaultBranchThroughApiCall $createRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient     = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(RequestFactoryInterface::class);
        $this->apiToken       = 'apiToken' . SecureRandom\string(8);
        $this->createRelease  = new SetDefaultBranchThroughApiCall(
            $this->messageFactory,
            $this->httpClient,
            $this->apiToken
        );
    }

    /**
     * @psalm-param positive-int $responseCode
     *
     * @dataProvider exampleValidResponseCodes
     */
    public function testSuccessfulRequest(int $responseCode): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('PATCH', 'https://api.github.com/repos/foo/bar')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = (new Response())
            ->withStatus($responseCode);

        $validResponse->getBody()
            ->write('{"default_branch": "foo-bar-baz"}');

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame(
                    [
                        'Host'          => ['the-domain.com'],
                        'Content-Type'  => ['application/json'],
                        'User-Agent'    => ['Ocramius\'s minimal API V3 client'],
                        'Authorization' => ['token ' . $this->apiToken],
                    ],
                    $request->getHeaders()
                );

                self::assertJsonStringEqualsJsonString(
                    '{"default_branch": "foo-bar-baz"}',
                    $request->getBody()
                        ->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->createRelease->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz')
        );
    }

    /** @psalm-return non-empty-list<array{positive-int}> */
    public function exampleValidResponseCodes(): array
    {
        return [
            [200],
            [201],
            [204],
        ];
    }

    public function testRequestFailedToSwitchBranch(): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('PATCH', 'https://api.github.com/repos/foo/bar')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

        $validResponse->getBody()
            ->write('{"default_branch": "not-what-we-expected"}');

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame(
                    [
                        'Host'          => ['the-domain.com'],
                        'Content-Type'  => ['application/json'],
                        'User-Agent'    => ['Ocramius\'s minimal API V3 client'],
                        'Authorization' => ['token ' . $this->apiToken],
                    ],
                    $request->getHeaders()
                );

                self::assertJsonStringEqualsJsonString(
                    '{"default_branch": "foo-bar-baz"}',
                    $request->getBody()
                        ->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('foo-bar-baz');

        $this->createRelease->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz')
        );
    }

    /**
     * @psalm-param positive-int $responseCode
     *
     * @dataProvider exampleFailureResponseCodes
     */
    public function testRequestFailedToSwitchBranchDueToInvalidResponseCode(int $responseCode): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('PATCH', 'https://api.github.com/repos/foo/bar')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = (new Response())
            ->withStatus($responseCode);

        $validResponse->getBody()
            ->write('{"default_branch": "foo-bar-baz"}');

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame(
                    [
                        'Host'          => ['the-domain.com'],
                        'Content-Type'  => ['application/json'],
                        'User-Agent'    => ['Ocramius\'s minimal API V3 client'],
                        'Authorization' => ['token ' . $this->apiToken],
                    ],
                    $request->getHeaders()
                );

                self::assertJsonStringEqualsJsonString(
                    '{"default_branch": "foo-bar-baz"}',
                    $request->getBody()
                        ->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Failed to set default branch through GitHub API.');

        $this->createRelease->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz')
        );
    }

    /** @psalm-return non-empty-list<array{positive-int}> */
    public function exampleFailureResponseCodes(): array
    {
        return [
            [199],
            [400],
            [401],
            [500],
        ];
    }
}
