<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use DateTimeImmutable;
use Laminas\AutomaticReleases\Changelog\ChangelogExistsViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog\Response\Milestone;
use Laminas\AutomaticReleases\Github\CreateReleaseTextViaKeepAChangelog;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\Clock\SystemClock;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;

use function array_merge;
use function Psl\Str\format;
use function Psl\Type\bool;
use function Psl\Type\dict;
use function Psl\Type\mixed;
use function Psl\Type\non_empty_string;
use function Psl\Type\null as nullAlias;
use function Psl\Type\positive_int;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\union;
use function Psl\Type\vec;

/** @psalm-suppress MissingConstructor */
final class CreateReleaseTextViaKeepAChangelogTest extends TestCase
{
    private FrozenClock $frozenClock;
    private SystemClock $systemClock;

    public function setUp(): void
    {
        $this->systemClock = SystemClock::fromSystemTimezone();
        $this->frozenClock = new FrozenClock(new DateTimeImmutable('2020-01-01'));
    }

    public function testReportsCannotCreateReleaseTextIfChangelogFileIsMissing(): void
    {
        $repositoryPath = $this->createMockRepositoryWithChangelog(
            self::INVALID_CHANGELOG,
            'NOT-A-CHANGELOG.md'
        );
        $workingPath    = $this->checkoutMockRepositoryWithChangelog($repositoryPath);

        self::assertFalse(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole(), $this->frozenClock))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $workingPath
                )
        );
    }

    public function testReportsCannotCreateReleaseTextIfChangelogFileDoesNotContainVersion(): void
    {
        $repositoryPath = $this->createMockRepositoryWithChangelog(self::INVALID_CHANGELOG);
        $workingPath    = $this->checkoutMockRepositoryWithChangelog($repositoryPath);

        self::assertFalse(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole(), $this->frozenClock))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $workingPath
                )
        );
    }

    public function testReportsCanCreateReleaseWhenChangelogWithVersionExists(): void
    {
        $changelogContents = Str\format(self::READY_CHANGELOG, $this->systemClock->now()->format('Y-m-d'));
        $repositoryPath    = $this->createMockRepositoryWithChangelog(
            $changelogContents,
        );
        $workingPath       = $this->checkoutMockRepositoryWithChangelog($repositoryPath);

        self::assertTrue(
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole(), $this->frozenClock))
                ->canCreateReleaseText(
                    $this->createMockMilestone(),
                    RepositoryName::fromFullName('example/repo'),
                    SemVerVersion::fromMilestoneName('1.0.0'),
                    BranchName::fromName('1.0.x'),
                    $workingPath
                )
        );
    }

    public function testExtractsReleaseTextViaChangelogFile(): void
    {
        $date              = $this->frozenClock->now()->format('Y-m-d');
        $changelogContents = format(self::READY_CHANGELOG, $date);
        $repositoryPath    = $this->createMockRepositoryWithChangelog($changelogContents);
        $workingPath       = $this->checkoutMockRepositoryWithChangelog($repositoryPath);

        $expected = format(<<< 'END'
            ## 1.0.0 - %s
            
            ### Added
            
            - Everything.
            END, $date);

        self::assertStringContainsString(
            $expected,
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole(), $this->frozenClock))(
                $this->createMockMilestone(),
                RepositoryName::fromFullName('example/repo'),
                SemVerVersion::fromMilestoneName('1.0.0'),
                BranchName::fromName('1.0.x'),
                $workingPath
            )
                ->contents()
        );
    }

    public function testExtractsNonEmptySectionsForVersionViaChangelogFile(): void
    {
        $date              = $this->frozenClock->now()->format('Y-m-d');
        $changelogContents = format(self::CHANGELOG_MULTI_SECTION, $date);
        $repositoryPath    = $this->createMockRepositoryWithChangelog(
            $changelogContents,
            'CHANGELOG.md',
            '2.3.x'
        );
        $workingPath       = $this->checkoutMockRepositoryWithChangelog($repositoryPath);

        $expected = format(<<< 'END'
            ## 2.3.12 - %s
            
            ### Added
            
            - Something.

            ### Fixed

            - Several things
            END, $date);

        self::assertStringContainsString(
            $expected,
            (new CreateReleaseTextViaKeepAChangelog(new ChangelogExistsViaConsole(), $this->frozenClock))(
                $this->createMockMilestone(['title' => 'example']),
                RepositoryName::fromFullName('example/repo'),
                SemVerVersion::fromMilestoneName('2.3.12'),
                BranchName::fromName('2.3.x'),
                $workingPath
            )
                ->contents()
        );
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private function createMockMilestone(array $fields = []): Milestone
    {
        return Milestone::fromPayload(
            shape([
                'number' => positive_int(),
                'closed' => bool(),
                'title' => non_empty_string(),
                'description' => union(nullAlias(), string()),
                'url' => non_empty_string(),
                'issues' => shape([
                    'nodes' => vec(dict(string(), mixed())),
                ]),
                'pullRequests' => shape([
                    'nodes' => vec(dict(string(), mixed())),
                ]),
            ])->assert(
                array_merge([
                    'number'       => 123,
                    'closed'       => true,
                    'title'        => '1.2.3',
                    'description'  => 'The description',
                    'issues'       => [
                        'nodes' => [],
                    ],
                    'pullRequests' => [
                        'nodes' => [],
                    ],
                    'url'          => 'https://github.com/vendor/project/releases/milestone/123',
                ], $fields)
            )
        );
    }

    /**
     * @psalm-return non-empty-string
     */
    private function createMockRepositoryWithChangelog(
        string $template,
        string $filename = 'CHANGELOG.md',
        string $initialBranch = '1.0.x'
    ): string {
        $repo = $this->createTemporaryFile('CreateReleaseTextViaKeepAChangelog');
        Filesystem\delete_file($repo);
        Filesystem\create_directory($repo);

        Filesystem\write_file(Str\format('%s/%s', $repo, $filename), $template);

        Shell\execute('git', ['init', '.'], $repo);
        Shell\execute('git', ['add', '.'], $repo);
        Shell\execute('git', ['config', 'user.email', 'me@example.com'], $repo);
        Shell\execute('git', ['config', 'user.name', 'Just Me'], $repo);
        Shell\execute('git', ['commit', '-m', 'Initial import'], $repo);
        Shell\execute('git', ['switch', '-c', $initialBranch], $repo);

        return non_empty_string()->assert($repo);
    }

    /**
     * @psalm-param non-empty-string $origin
     *
     * @psalm-return non-empty-string
     */
    private function checkoutMockRepositoryWithChangelog(string $origin): string
    {
        $repo = $this->createTemporaryFile('CreateReleaseTextViaKeepAChangelog');
        Filesystem\delete_file($repo);

        Shell\execute('git', ['clone', $origin, $repo]);

        return non_empty_string()->assert($repo);
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
        
        ## 0.1.0 - 2019-01-01
        
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

    private const CHANGELOG_MULTI_SECTION = <<< 'END'
        # Changelog
        
        All notable changes to this project will be documented in this file, in reverse chronological order by release.
                
        ## 2.3.12 - %s
        
        ### Added
        
        - Something.
        
        ### Changed
        
        - Nothing.
        
        ### Deprecated
        
        - Nothing.
        
        ### Removed
        
        - Nothing.
        
        ### Fixed
        
        - Several things
        
        ## 0.1.0 - 2019-01-01
        
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
