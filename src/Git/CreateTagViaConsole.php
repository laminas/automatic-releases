<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use Symfony\Component\Process\Process;

use function Safe\file_put_contents;
use function Safe\tempnam;
use function sys_get_temp_dir;

final class CreateTagViaConsole implements CreateTag
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId
    ): void {
        $tagFileName = tempnam(sys_get_temp_dir(), 'created_tag');

        file_put_contents($tagFileName, $changelog);

        (new Process(['git', 'checkout', $sourceBranch->name()], $repositoryDirectory))
            ->mustRun();

        (new Process(
            ['git', 'tag', $tagName, '-F', $tagFileName, '--cleanup=whitespace', '--local-user=' . $keyId->id()],
            $repositoryDirectory
        ))->mustRun();
    }
}
