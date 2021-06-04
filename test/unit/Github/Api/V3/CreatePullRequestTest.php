<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\V3;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\SecureRandom;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall */
final class CreatePullRequestTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;

    /** @var MockObject&RequestFactoryInterface */
    private RequestFactoryInterface $messageFactory;

    /** @psalm-var non-empty-string */
    private string $apiToken;

    private CreatePullRequestThroughApiCall $createPullRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient     = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(RequestFactoryInterface::class);

        $this->apiToken          = 'apiToken' . SecureRandom\string(8);
        $this->createPullRequest = new CreatePullRequestThroughApiCall(
            $this->messageFactory,
            $this->httpClient,
            $this->apiToken
        );
    }

    public function testSuccessfulRequest(): void
    {
        $this->messageFactory
            ->expects(self::any())
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/pulls')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

        $validResponse->getBody()->write(
            <<<'JSON'
            {
                "url": "http://another-domain.com/the-pr"
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
                        "title": "the-title",
                        "head": "the/source-branch",
                        "base": "the/target-branch",
                        "body": "the-body",
                        "maintainer_can_modify": true,
                        "draft": false
                    }
                    JSON,
                    $request->getBody()->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->createPullRequest->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('the/source-branch'),
            BranchName::fromName('the/target-branch'),
            'the-title',
            'the-body'
        );
    }
}
