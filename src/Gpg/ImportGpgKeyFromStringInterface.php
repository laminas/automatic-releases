<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

interface ImportGpgKeyFromStringInterface
{
    public function __invoke(string $keyContents): SecretKeyId;
}
