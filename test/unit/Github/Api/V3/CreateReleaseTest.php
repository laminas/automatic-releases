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
use Psl\SecureRandom;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall */
final class CreateReleaseTest extends TestCase
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
            $this->apiToken
        );
    }

    public function testSuccessfulRequest(): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/releases')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

        $validResponse->getBody()->write(
            <<<'JSON'
            {
                "html_url": "https://another-domain.com/the-pr"
            }
            JSON
        );

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
                    <<<'JSON'
                    {
                        "tag_name": "1.2.3",
                        "name": "1.2.3",
                        "body": "the-body"
                    }
                    JSON,
                    $request->getBody()->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        self::assertEquals(
            'https://another-domain.com/the-pr',
            $this->createRelease->__invoke(
                RepositoryName::fromFullName('foo/bar'),
                SemVerVersion::fromMilestoneName('1.2.3'),
                'the-body'
            )
        );
    }
}
