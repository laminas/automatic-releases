<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;

use function Psl\Env\temp_dir;
use function Psl\File\write;
use function Psl\Filesystem\create_temporary_file;
use function Psl\Shell\execute;

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

        $tagFileName = create_temporary_file(temp_dir(), 'created_tag');

        write($tagFileName, $changelog);

        execute('git', ['checkout', $sourceBranch->name()], $repositoryDirectory);

        execute(
            'git',
            ['tag', $tagName, '-F', $tagFileName, '--cleanup=whitespace', '--local-user=' . $keyId->id()],
            $repositoryDirectory,
        );
    }
}
