<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Gpg;

interface ImportGpgKeyFromString
{
    public function __invoke(string $keyContents): SecretKeyId;
}
