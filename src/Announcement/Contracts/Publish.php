<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Announcement\Contracts;

interface Publish
{
    public function __invoke(Announcement $message): void;
}
