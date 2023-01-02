<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Psl\Shell;

use function str_contains;
use function trim;

final class HasTagViaConsole implements HasTag
{
    public function __invoke(string $repositoryDirectory, string $tagName): bool
    {
        $output = Shell\execute('git', ['tag', '--list', $tagName], $repositoryDirectory);

        if (trim($output) === '') {
            return false;
        }

        return str_contains($output, $tagName);
    }
}
