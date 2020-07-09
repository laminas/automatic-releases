<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Github\Api\V3;

use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateReleaseThroughApiCall;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use function uniqid;

final class CreateReleaseTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private $httpClient;

    /** @var RequestFactoryInterface&MockObject */
    private $messageFactory;

    /** @var string */
    private $apiToken;

    /** @var CreateReleaseThroughApiCall */
    private $createRelease;

    protected function setUp() : void
    {
        parent::setUp();

        $this->httpClient     = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(RequestFactoryInterface::class);
        $this->apiToken       = uniqid('apiToken', true);
        $this->createRelease  = new CreateReleaseThroughApiCall(
            $this->messageFactory,
            $this->httpClient,
            $this->apiToken
        );
    }

    public function testSuccessfulRequest() : void
    {
        $this
            ->messageFactory
            ->expects(self::any())
            ->method('createRequest')
            ->with('POST', 'https://api.github.com/repos/foo/bar/releases')
            ->willReturn(new Request('https://the-domain.com/the-path'));

        $validResponse = new Response();

        $validResponse->getBody()->write(<<<'JSON'
{
    "html_url": "http://another-domain.com/the-pr"
}
JSON
        );

        $this
            ->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request) : bool {
                self::assertSame(
                    [
                        'Host'          => ['the-domain.com'],
                        'Content-Type'  => ['application/json'],
                        'User-Agent'    => ['Ocramius\'s minimal API V3 client'],
                        'Authorization' => ['bearer ' . $this->apiToken],
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
JSON
                    ,
                    $request->getBody()->__toString()
                );

                return true;
            }))
            ->willReturn($validResponse);

        self::assertEquals(
            'http://another-domain.com/the-pr',
            $this->createRelease->__invoke(
                RepositoryName::fromFullName('foo/bar'),
                SemVerVersion::fromMilestoneName('1.2.3'),
                'the-body'
            )
        );
    }
}
