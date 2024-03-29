<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall;
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

use function sprintf;

use const PHP_EOL;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall */
final class CreateReleaseThroughApiCallTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    /** @var RequestFactoryInterface&MockObject */
    private RequestFactoryInterface $messageFactory;
    /** @psalm-var non-empty-string */
    private string $apiToken;
    private CreateReleaseThroughApiCall $createRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient     = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(RequestFactoryInterface::class);
        $this->apiToken       = 'apiToken' . SecureRandom\string(8);
        $this->createRelease  = new CreateReleaseThroughApiCall(
            $this->messageFactory,
            $this->httpClient,
            $this->apiToken,
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
            ->with('POST', 'https://api.github.com/repos/foo/bar/releases')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = (new Response())
            ->withStatus($responseCode);

        $validResponse->getBody()
            ->write('{"html_url": "http://the-domain.com/release"}');

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
                    $request->getHeaders(),
                );

                self::assertJsonStringEqualsJsonString(
                    <<<'JSON'
{
    "body": "A description for my awesome release",
    "name": "1.2.3",
    "tag_name": "1.2.3"
}
JSON
                    ,
                    $request->getBody()
                        ->__toString(),
                );

                return true;
            }))
            ->willReturn($validResponse);

        self::assertEquals(
            'http://the-domain.com/release',
            $this->createRelease->__invoke(
                RepositoryName::fromFullName('foo/bar'),
                SemVerVersion::fromMilestoneName('1.2.3'),
                'A description for my awesome release',
            ),
        );
    }

    /** @psalm-return array<int,array<array-key,positive-int>> */
    public function exampleValidResponseCodes(): array
    {
        return [
            200 => [200],
            201 => [201],
            204 => [204],
        ];
    }

    /**
     * @psalm-param positive-int $responseCode
     *
     * @dataProvider exampleFailureResponseCodes
     */
    public function testRequestFailedToCreateReleaseDueToInvalidResponseCode(int $responseCode): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/releases')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $invalidResponse = (new Response())
            ->withStatus($responseCode);

        // `message` field describing the error
        // https://docs.github.com/en/rest/overview/resources-in-the-rest-api#client-errors
        $invalidResponse->getBody()->write(
            sprintf('{"message": "%s"}', 'Error code ' . $responseCode),
        );

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($invalidResponse);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage(
            'Failed to create release through GitHub API;' . PHP_EOL
            . 'Status code: ' . $responseCode . PHP_EOL
            . 'Message: Error code ' . $responseCode . PHP_EOL,
        );

        ($this->createRelease)(
            RepositoryName::fromFullName('foo/bar'),
            SemVerVersion::fromMilestoneName('1.2.3'),
            'A description for my awesome release',
        );
    }

    /** @psalm-return array<int,array<array-key,positive-int>> */
    public function exampleFailureResponseCodes(): array
    {
        return [
            199 => [199],
            400 => [400],
            401 => [401],
            500 => [500],
        ];
    }

    public function testRequestFailedToCreateReleaseDueToInvalidResponseBody(): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/releases')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $invalidResponse = (new Response())
            ->withStatus(200);

        $invalidResponse->getBody()
            ->write('{"invalid": "response"}');

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($invalidResponse);

        $this->expectException(DecodeException::class);
        $this->expectExceptionMessage('"array{\'html_url\': non-empty-string}"');

        $this->createRelease->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            SemVerVersion::fromMilestoneName('1.2.3'),
            'A description for my awesome release',
        );
    }
}
