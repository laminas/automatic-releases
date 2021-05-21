<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;

final class CreateTagViaConsole implements CreateTag
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId
    ): void {
        $tagFileName = Filesystem\create_temporary_file(Env\temp_dir(), 'created_tag');

        Filesystem\write_file($tagFileName, $changelog);

        Shell\execute('git', ['checkout', $sourceBranch->name()], $repositoryDirectory);
        Shell\execute('git', ['tag', $tagName, '-F', $tagFileName, '--cleanup=whitespace', '--local-user=' . $keyId->id()], $repositoryDirectory);
    }
}
