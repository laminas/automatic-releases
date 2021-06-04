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
use Psl\Exception\InvariantViolationException;
use Psl\Json\Exception\DecodeException;
use Psl\SecureRandom;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequestThroughApiCall */
final class CreatePullRequestThroughApiCallTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    /** @var RequestFactoryInterface&MockObject */
    private RequestFactoryInterface $messageFactory;
    /** @psalm-var non-empty-string */
    private string $apiToken;
    private CreatePullRequestThroughApiCall $createPullRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient        = $this->createMock(ClientInterface::class);
        $this->messageFactory    = $this->createMock(RequestFactoryInterface::class);
        $this->apiToken          = 'apiToken' . SecureRandom\string(8);
        $this->createPullRequest = new CreatePullRequestThroughApiCall(
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
            ->with('POST', 'https://api.github.com/repos/foo/bar/pulls')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = (new Response())
            ->withStatus($responseCode);

        $validResponse->getBody()
            ->write('{"url": "http://the-domain.com/pull-request"}');

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
    "base": "target-branch-name",
    "body": "A description for my awesome pull request",
    "draft": false,
    "head": "foo-bar-baz",
    "maintainer_can_modify": true,
    "title": "My awesome pull request"
}
JSON
                    ,
                    $request->getBody()
                        ->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        $this->createPullRequest->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz'),
            BranchName::fromName('target-branch-name'),
            'My awesome pull request',
            'A description for my awesome pull request'
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

    /**
     * @psalm-param positive-int $responseCode
     *
     * @dataProvider exampleFailureResponseCodes
     */
    public function testRequestFailedToCreatePullRequestDueToInvalidResponseCode(int $responseCode): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/pulls')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $invalidResponse = (new Response())
            ->withStatus($responseCode);

        $invalidResponse->getBody()
            ->write('{"url": "http://the-domain.com/pull-request"}');

        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($invalidResponse);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Failed to create pull request through GitHub API.');

        $this->createPullRequest->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz'),
            BranchName::fromName('target-branch-name'),
            'My awesome pull request',
            'A description for my awesome pull request'
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

    public function testRequestFailedToCreatePullRequestDueToInvalidResponseBody(): void
    {
        $this->messageFactory
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/pulls')
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
        $this->expectExceptionMessage('"array{\'url\': mixed}"');

        $this->createPullRequest->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz'),
            BranchName::fromName('target-branch-name'),
            'My awesome pull request',
            'A description for my awesome pull request'
        );
    }
}
