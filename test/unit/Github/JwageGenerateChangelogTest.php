<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Github;

use ChangelogGenerator\ChangelogConfig;
use ChangelogGenerator\ChangelogGenerator;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Github\JwageGenerateChangelog;
use Laminas\AutomaticReleases\Github\Value\RepositoryName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class JwageGenerateChangelogTest extends TestCase
{
    public function testGenerateChangelog(): void
    {
        $config = (new ChangelogConfig())
            ->setUser('doctrine')
            ->setRepository('repository-name')
            ->setMilestone('1.0.0');

        $output = new BufferedOutput();

        $changelogGenerator = $this->createMock(ChangelogGenerator::class);

        $changelogGenerator->expects(self::once())
            ->method('generate')
            ->with($config, $output);

        $repositoryName = RepositoryName::fromFullName('doctrine/repository-name');
        $semVerVersion  = SemVerVersion::fromMilestoneName('1.0.0');

        (new JwageGenerateChangelog($changelogGenerator))
            ->__invoke($repositoryName, $semVerVersion);
    }
}
