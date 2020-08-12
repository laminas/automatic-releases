<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\CreateReleaseTextViaKeepAChangelog;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function date;
use function file_put_contents;
use function Safe\tempnam;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

class CreateReleaseTextViaKeepAChangelogTest extends TestCase
{
    public function testReportsCannotCreateReleaseTextIfChangelogFileIsMissing(): void
    {
        $repositoryPath = $this->createMockRepositoryWithChangelog(
            self::INVALID_CHANGELOG,
            'NOT-A-CHANGELOG.md'
        );

        self::assertFalse(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole()))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $repositoryPath
                )
        );
    }

    public function testReportsCannotCreateReleaseTextIfChangelogFileDoesNotContainVersion(): void
    {
        $repositoryPath = $this->createMockRepositoryWithChangelog(
            self::INVALID_CHANGELOG,
            'CHANGELOG.md'
        );

        self::assertFalse(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole()))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $repositoryPath
                )
        );
    }

    public function testReportsCanCreateReleaseWhenChangelogWithVersionExists(): void
    {
        $changelogContents = sprintf(self::READY_CHANGELOG, date('Y-m-d'));
        $repositoryPath    = $this->createMockRepositoryWithChangelog(
            $changelogContents,
            'CHANGELOG.md'
        );

        self::assertTrue(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole()))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $repositoryPath
                )
        );
    }

    public function testExtractsReleaseTextViaChangelogFile(): void
    {
        $date              = date('Y-m-d');
        $changelogContents = sprintf(self::READY_CHANGELOG, $date);
        $repositoryPath    = $this->createMockRepositoryWithChangelog(
            $changelogContents,
            'CHANGELOG.md'
        );

        $expected = sprintf(<<< 'END'
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
            END, $date);

        self::assertSame(
            $expected,
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole()))
                ->__invoke(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $repositoryPath
                )
        );
    }

    private function createMockMilestone(): Milestone
    {
        return Milestone::fromPayload([
            'number'       => 123,
            'closed'       => true,
            'title'        => 'The title',
            'description'  => 'The description',
            'issues'       => [
                'nodes' => [],
            ],
            'pullRequests' => [
                'nodes' => [],
            ],
            'url'          => 'http://example.com/milestone',
        ]);
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockRepositoryWithChangelog(
        string $template,
        string $filename = 'CHANGELOG.md'
    ): string {
        $repo = tempnam(sys_get_temp_dir(), 'CreateReleaseTextViaKeepAChangelog');
        Assert::notEmpty($repo);
        unlink($repo);

        (new Process(['mkdir', '-p', $repo]))->mustRun();

        file_put_contents(
            sprintf('%s/%s', $repo, $filename),
            $template
        );

        (new Process(['git', 'init', '.'], $repo))->mustRun();
        (new Process(['git', 'add', '.'], $repo))->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $repo))->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $repo))->mustRun();
        (new Process(['git', 'commit', '-m', 'Initial import'], $repo))->mustRun();
        (new Process(['git', 'switch', '-c', '1.0.x'], $repo))->mustRun();

        return $repo;
    }

    private const INVALID_CHANGELOG = <<< 'END'
        # NOT A CHANGELOG

        This file is not a changelog.

        ## Bad headers

        It contains bad headers, among other things.

        END;

    private const READY_CHANGELOG = <<< 'END'
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
        
        END;
}
