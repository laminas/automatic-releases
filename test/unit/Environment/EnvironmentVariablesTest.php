<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Environment;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Dict;
use Psl\Env;
use Psl\Exception\InvariantViolationException;
use Psl\SecureRandom;
use function Psl\Env\set_var;

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

    /** @var array<non-empty-string, ?string> */
    private array $originalValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalValues = Dict\associate(self::RESET_ENVIRONMENT_VARIABLES, Dict\map(
            self::RESET_ENVIRONMENT_VARIABLES,
            static fn (string $variable) => Env\get_var($variable),
        ));
    }

    protected function tearDown(): void
    {
        $originalValues = $this->originalValues;

        Dict\map_with_key($originalValues, static function (string $key, string|null $value): void {
            if ($value === null) {
                Env\remove_var($key);

                return;
            }

            set_var($key, $value);
        });

        parent::tearDown();
    }

    public function testReadsEnvironmentVariables(): void
    {
        $signingSecretKey   = 'signingSecretKey' . SecureRandom\string(8);
        $signingSecretKeyId = SecretKeyId::fromBase16String('aabbccdd');
        $githubToken        = 'githubToken' . SecureRandom\string(8);
        $githubOrganisation = 'githubOrganisation' . SecureRandom\string(8);
        $gitAuthorName      = 'gitAuthorName' . SecureRandom\string(8);
        $gitAuthorEmail     = 'gitAuthorEmail' . SecureRandom\string(8);
        $githubEventPath    = 'githubEventPath' . SecureRandom\string(8);
        $githubWorkspace    = 'githubWorkspace' . SecureRandom\string(8);

        set_var('GITHUB_TOKEN', $githubToken);
        set_var('SIGNING_SECRET_KEY', $signingSecretKey);
        set_var('GITHUB_ORGANISATION', $githubOrganisation);
        set_var('GIT_AUTHOR_NAME', $gitAuthorName);
        set_var('GIT_AUTHOR_EMAIL', $gitAuthorEmail);
        set_var('GITHUB_EVENT_PATH', $githubEventPath);
        set_var('GITHUB_WORKSPACE', $githubWorkspace);

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->with($signingSecretKey)
            ->willReturn($signingSecretKeyId);

        $variables = EnvironmentVariables::fromEnvironment($importKey);

        self::assertEquals($signingSecretKeyId, $variables->signingSecretKey());
        self::assertSame($githubToken, $variables->githubToken());
        self::assertSame($gitAuthorName, $variables->gitAuthorName());
        self::assertSame($gitAuthorEmail, $variables->gitAuthorEmail());
        self::assertSame($githubEventPath, $variables->githubEventPath());
        self::assertSame($githubWorkspace, $variables->githubWorkspacePath());
    }

    public function testFailsOnMissingEnvironmentVariables(): void
    {
        set_var('GITHUB_TOKEN', '');
        set_var('SIGNING_SECRET_KEY', 'aaa');
        set_var('GITHUB_ORGANISATION', 'bbb');
        set_var('GIT_AUTHOR_NAME', 'ccc');
        set_var('GIT_AUTHOR_EMAIL', 'ddd@eee.ff');
        set_var('GITHUB_EVENT_PATH', '/tmp/event');
        set_var('GITHUB_WORKSPACE', '/tmp');

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->willReturn(SecretKeyId::fromBase16String('aabbccdd'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Could not find a value for environment variable "GITHUB_TOKEN"');

        EnvironmentVariables::fromEnvironment($importKey);
    }

    public function testDebugModeOffEnvironmentVariables(): void
    {
        // missing env variable.
        Env\remove_var('ACTIONS_RUNNER_DEBUG');

        set_var('GITHUB_TOKEN', 'token');
        set_var('SIGNING_SECRET_KEY', 'aaa');
        set_var('GITHUB_ORGANISATION', 'bbb');
        set_var('GIT_AUTHOR_NAME', 'ccc');
        set_var('GIT_AUTHOR_EMAIL', 'ddd@eee.ff');
        set_var('GITHUB_EVENT_PATH', '/tmp/event');
        set_var('GITHUB_WORKSPACE', '/tmp');

        $importKey = $this->createMock(ImportGpgKeyFromString::class);
        $importKey->method('__invoke')->willReturn(SecretKeyId::fromBase16String('aabbccdd'));
        $variables = EnvironmentVariables::fromEnvironment($importKey);
        self::assertSame('INFO', $variables->logLevel());
    }

    public function testDebugModeOnEnvironmentVariables(): void
    {
        set_var('ACTIONS_RUNNER_DEBUG', 'TRUE');
        set_var('GITHUB_TOKEN', 'token');
        set_var('SIGNING_SECRET_KEY', 'aaa');
        set_var('GITHUB_ORGANISATION', 'bbb');
        set_var('GIT_AUTHOR_NAME', 'ccc');
        set_var('GIT_AUTHOR_EMAIL', 'ddd@eee.ff');
        set_var('GITHUB_EVENT_PATH', '/tmp/event');
        set_var('GITHUB_WORKSPACE', '/tmp');

        $importKey = $this->createMock(ImportGpgKeyFromString::class);
        $importKey->method('__invoke')->willReturn(SecretKeyId::fromBase16String('aabbccdd'));
        $variables = EnvironmentVariables::fromEnvironment($importKey);

        self::assertSame('DEBUG', $variables->logLevel());
    }

    public function testInvalidLogLevelEnvironmentVariables(): void
    {
        $this->setupRequiredEnvironmentVariables();

        set_var('LOG_LEVEL', 'TEMP');

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage(
            'LOG_LEVEL env MUST be a valid monolog/monolog log level constant name or value; see https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels'
        );

        $importKey = $this->createMock(ImportGpgKeyFromString::class);
        $importKey->method('__invoke')
            ->willReturn(SecretKeyId::fromBase16String('aabbccdd'));

        EnvironmentVariables::fromEnvironment($importKey);
    }

    private function setupRequiredEnvironmentVariables(): void
    {
        set_var('GITHUB_TOKEN', 'token');
        set_var('SIGNING_SECRET_KEY', 'aaa');
        set_var('GITHUB_ORGANISATION', 'bbb');
        set_var('GIT_AUTHOR_NAME', 'ccc');
        set_var('GIT_AUTHOR_EMAIL', 'ddd@eee.ff');
        set_var('GITHUB_EVENT_PATH', '/tmp/event');
        set_var('GITHUB_WORKSPACE', '/tmp');
    }
}
