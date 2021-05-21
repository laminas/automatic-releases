<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Changelog;

use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;
use Psl\Type;

class ChangelogExistsViaConsoleTest extends TestCase
{
    public function testReturnsFalseWhenChangelogIsNotPresentInBranch(): void
    {
        $repository = $this->createMockRepositoryWithChangelog();
        $workingDir = $this->checkoutMockRepositoryWithChangelog($repository);
        self::assertFalse(
            (new ChangelogExistsViaConsole())(
                BranchName::fromName('0.99.x'),
                $workingDir
            )
        );
    }

    public function testReturnsTrueWhenChangelogIsPresentInBranch(): void
    {
        $repository = $this->createMockRepositoryWithChangelog();
        $workingDir = $this->checkoutMockRepositoryWithChangelog($repository);
        self::assertTrue(
            (new ChangelogExistsViaConsole())(
                BranchName::fromName('1.0.x'),
                $workingDir
            )
        );
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockRepositoryWithChangelog(): string
    {
        $repo = Filesystem\create_temporary_file(Env\temp_dir(), 'ChangelogExists');
        Filesystem\delete_file($repo);

        Filesystem\create_directory($repo);

        Filesystem\write_file(
            Str\format('%s/%s', $repo, 'CHANGELOG.md'),
            <<< 'CHANGELOG'
                # Changelog
                
                All notable changes to this project will be documented in this file, in reverse chronological order by release.
                        
                ## 1.0.0 - %s
                
                ### Added
                
                - Everything.
                
                ### Changed
                
                - Nothing.
                
                ### Deprecated
                
                - Nothing.
                
                ### Removed
                
                - Nothing.
                
                ### Fixed
                
                - Nothing.
                
                CHANGELOG
        );

        Shell\execute('git', ['init', '.'], $repo);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $repo);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $repo);
        Shell\execute('git', ['add', '.'], $repo);
        Shell\execute('git', ['commit', '-m', 'Initial import'], $repo);
        Shell\execute('git', ['switch', '-c', '1.0.x'], $repo);

        return Type\non_empty_string()->assert($repo);
    }

    /**
     * @psalm-param non-empty-string $origin
     *
     * @psalm-return non-empty-string
     */
    private function checkoutMockRepositoryWithChangelog(string $origin): string
    {
        $repo = Filesystem\create_temporary_file(Env\temp_dir(), 'CreateReleaseTextViaKeepAChangelog');
        Filesystem\delete_file($repo);

        Shell\execute('git', ['clone', $origin, $repo]);

        return Type\non_empty_string()->assert($repo);
    }
}
