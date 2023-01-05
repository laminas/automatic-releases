<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Git;

use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Psl\Env;
use Psl\File;
use Psl\Filesystem;
use Psl\Shell;
use Psr\Log\LoggerInterface;

use function sprintf;

final class CreateTagViaConsole implements CreateTag
{
    public function __construct(
        private readonly HasTag $hasTag,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        string $repositoryDirectory,
        BranchName $sourceBranch,
        string $tagName,
        string $changelog,
        SecretKeyId $keyId,
    ): void {
        if (($this->hasTag)($repositoryDirectory, $tagName)) {
            $this->logger->info(
                sprintf('[CreateTagViaConsole] Skipping this step; tag "%s" already exists.', $tagName),
            );

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
