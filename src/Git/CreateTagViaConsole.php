<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Env;
use Psl\File;
use Psl\Filesystem;
use Psl\Shell;

use function sprintf;

final class CreateTagViaConsole implements CreateTag
{
    public function __construct(private HasTag $hasTag)
    {
    }

    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId,
    ): void {
        if (($this->hasTag)($repositoryDirectory, $tagName) === true) {
            return;
        }

        $tagFileName = Filesystem\create_temporary_file(Env\temp_dir(), 'created_tag');

        File\write($tagFileName, $changelog);

        Shell\execute('git', ['checkout', $sourceBranch->name()], $repositoryDirectory);

        Shell\execute(
            'git',
            ['tag', $tagName, '-F', $tagFileName, '--cleanup=whitespace', '--local-user=' . $keyId->id()],
            $repositoryDirectory,
        );
    }
}
