<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Environment;

use Doctrine\AutomaticReleases\Environment\EnvironmentVariables;
use Doctrine\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Doctrine\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use function array_map;
use function array_walk;
use function getenv;
use function Safe\array_combine;
use function Safe\putenv;
use function uniqid;

final class EnvironmentVariablesTest extends TestCase
{
    private const RESET_ENVIRONMENT_VARIABLES = [
        'GITHUB_HOOK_SECRET',
        'GITHUB_TOKEN',
        'SIGNING_SECRET_KEY',
        'GITHUB_ORGANISATION',
        'GITHUB_ORGANISATION',
        'GIT_AUTHOR_NAME',
        'GIT_AUTHOR_EMAIL',
        'GITHUB_EVENT_PATH',
        'GITHUB_WORKSPACE',
    ];

    /** @var array<string, string|false> */
    private $originalValues = [];

    protected function setUp() : void
    {
        parent::setUp();

        $this->originalValues = array_combine(
            self::RESET_ENVIRONMENT_VARIABLES,
            array_map('getenv', self::RESET_ENVIRONMENT_VARIABLES)
        );
    }

    protected function tearDown() : void
    {
        array_walk($this->originalValues, static function ($value, string $key) : void {
            if ($value === false) {
                putenv($key . '=');

                return;
            }

            putenv($key . '=' . $value);
        });

        parent::tearDown();
    }

    public function testReadsEnvironmentVariables() : void
    {
        $signingSecretKey   = uniqid('signingSecretKey', true);
        $signingSecretKeyId = SecretKeyId::fromBase16String('aabbccdd');
        $githubToken        = uniqid('githubToken', true);
        $githubOrganisation = uniqid('githubOrganisation', true);
        $gitAuthorName      = uniqid('gitAuthorName', true);
        $gitAuthorEmail     = uniqid('gitAuthorEmail', true);
        $githubEventPath    = uniqid('githubEventPath', true);
        $githubWorkspace    = uniqid('githubWorkspace', true);

        putenv('GITHUB_TOKEN=' . $githubToken);
        putenv('SIGNING_SECRET_KEY=' . $signingSecretKey);
        putenv('GITHUB_ORGANISATION=' . $githubOrganisation);
        putenv('GIT_AUTHOR_NAME=' . $gitAuthorName);
        putenv('GIT_AUTHOR_EMAIL=' . $gitAuthorEmail);
        putenv('GITHUB_EVENT_PATH=' . $githubEventPath);
        putenv('GITHUB_WORKSPACE=' . $githubWorkspace);

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->with($signingSecretKey)
            ->willReturn($signingSecretKeyId);

        $variables = EnvironmentVariables::fromEnvironment($importKey);

        self::assertEquals($signingSecretKeyId, $variables->signingSecretKey());
        self::assertSame($githubToken, $variables->githubToken());
        self::assertSame($githubOrganisation, $variables->githubOrganisation());
        self::assertSame($gitAuthorName, $variables->gitAuthorName());
        self::assertSame($gitAuthorEmail, $variables->gitAuthorEmail());
        self::assertSame($githubEventPath, $variables->githubEventPath());
        self::assertSame($githubWorkspace, $variables->githubWorkspacePath());
    }

    public function testFailsOnMissingEnvironmentVariables() : void
    {
        putenv('GITHUB_TOKEN=');
        putenv('SIGNING_SECRET_KEY=aaa');
        putenv('GITHUB_ORGANISATION=bbb');
        putenv('GIT_AUTHOR_NAME=ccc');
        putenv('GIT_AUTHOR_EMAIL=ddd@eee.ff');
        putenv('GITHUB_EVENT_PATH=/tmp/event');
        putenv('GITHUB_WORKSPACE=/tmp');

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->willReturn(SecretKeyId::fromBase16String('aabbccdd'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find a value for environment variable "GITHUB_TOKEN"');

        EnvironmentVariables::fromEnvironment($importKey);
    }
}
