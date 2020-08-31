<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github\Api\V3;

use InvalidArgumentException;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Webmozart\Assert\Assert;

use function uniqid;

/** @covers \Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranchThroughApiCall */
final class SetDefaultBranchThroughApiCallTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private $httpClient;
    /** @var RequestFactoryInterface&MockObject */
    private $messageFactory;
    /** @psalm-var non-empty-string */
    private string $apiToken;
    private SetDefaultBranchThroughApiCall $createRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient     = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(RequestFactoryInterface::class);
        $apiToken             = uniqid('apiToken', true);

        Assert::notEmpty($apiToken);

        $this->apiToken      = $apiToken;
        $this->createRelease = new SetDefaultBranchThroughApiCall(
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
            ->with('PATCH', 'https://api.github.com/repos/foo/bar')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

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

    public function testRequestFailedToSwitchBranch(): void
    {
        $this->messageFactory
            ->expects(self::any())
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not-what-we-expected');

        $this->createRelease->__invoke(
            RepositoryName::fromFullName('foo/bar'),
            BranchName::fromName('foo-bar-baz')
        );
    }
}
