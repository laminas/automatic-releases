<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github\Value;

use Assert\Assert;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

final class RepositoryName
{
    /** @var string */
    private $owner;

    /** @var string */
    private $name;

    private function __construct()
    {
    }

    public static function fromFullName(string $fullName) : self
    {
        Assert
            ::that($fullName)
            ->notEmpty()
            ->regex('/^[a-zA-Z0-9_\\.-]+\\/[a-zA-Z0-9_\\.-]+$/');

        $instance = new self();

        [$instance->owner, $instance->name] = explode('/', $fullName);

        return $instance;
    }

    public function assertMatchesOwner(string $owner) : void
    {
        Assert
            ::that(strtolower($this->owner))
            ->same(strtolower($owner));
    }

    public function uriWithTokenAuthentication(string $token) : UriInterface
    {
        Assert
            ::that($token)
            ->notEmpty();

        return new Uri('https://' . $token . ':x-oauth-basic@github.com/' . $this->owner . '/' . $this->name . '.git');
    }

    public function owner() : string
    {
        return $this->owner;
    }

    public function name() : string
    {
        return $this->name;
    }
}
