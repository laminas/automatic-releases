<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Api\Hook;

use Assert\Assert;
use Psr\Http\Message\RequestInterface;
use function hash_equals;
use function hash_hmac;

final class VerifyRequestSignature
{
    public function __invoke(RequestInterface $request, string $secret) : void
    {
        $sha1 = hash_hmac('sha1', $request->getBody()->__toString(), $secret);

        Assert::that(hash_equals('sha1=' . $sha1, $request->getHeaderLine('X-Hub-Signature')))
              ->true();
    }
}
