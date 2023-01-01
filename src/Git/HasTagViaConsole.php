<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use function Psl\Shell\execute;
use function str_contains;
use function trim;

final class HasTagViaConsole implements HasTag
{
    public function __invoke(string $repositoryDirectory, string $tagName): bool
    {
        $outout = execute('git', ['tag', '--list', $tagName], $repositoryDirectory);

        if (trim($outout) === '') {
            return false;
        }

        return str_contains($outout, $tagName);
    }
}
