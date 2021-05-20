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

    /** @var array<string, ?string> */
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

        Dict\map_with_key($originalValues, static function (string $key, ?string $value): void {
            if ($value === null) {
                Env\remove_var($key);

                return;
            }

            Env\set_var($key, $value);
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

        Env\set_var('GITHUB_TOKEN', $githubToken);
        Env\set_var('SIGNING_SECRET_KEY', $signingSecretKey);
        Env\set_var('GITHUB_ORGANISATION', $githubOrganisation);
        Env\set_var('GIT_AUTHOR_NAME', $gitAuthorName);
        Env\set_var('GIT_AUTHOR_EMAIL', $gitAuthorEmail);
        Env\set_var('GITHUB_EVENT_PATH', $githubEventPath);
        Env\set_var('GITHUB_WORKSPACE', $githubWorkspace);

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
        Env\set_var('GITHUB_TOKEN', '');
        Env\set_var('SIGNING_SECRET_KEY', 'aaa');
        Env\set_var('GITHUB_ORGANISATION', 'bbb');
        Env\set_var('GIT_AUTHOR_NAME', 'ccc');
        Env\set_var('GIT_AUTHOR_EMAIL', 'ddd@eee.ff');
        Env\set_var('GITHUB_EVENT_PATH', '/tmp/event');
        Env\set_var('GITHUB_WORKSPACE', '/tmp');

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->willReturn(SecretKeyId::fromBase16String('aabbccdd'));

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Could not find a value for environment variable "GITHUB_TOKEN"');

        EnvironmentVariables::fromEnvironment($importKey);
    }
}
