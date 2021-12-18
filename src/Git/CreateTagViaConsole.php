<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Env;

use function Psl\Filesystem\create_temporary_file;
use function Psl\Filesystem\write_file;
use function Psl\Shell\execute;

final class CreateTagViaConsole implements CreateTag
{
    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $secretKeyId
    ): void {
        $tagFileName = create_temporary_file(Env\temp_dir(), 'created_tag');

        write_file($tagFileName, $changelog);

        execute('git', ['checkout', $sourceBranch->name()], $repositoryDirectory);
        execute('git', ['tag', $tagName, '-F', $tagFileName, '--cleanup=whitespace', '--local-user=' . $secretKeyId], $repositoryDirectory);
    }
}
