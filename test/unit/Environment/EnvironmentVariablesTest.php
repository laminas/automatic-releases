<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Environment;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

use function Psl\Dict\associate;
use function Psl\Dict\map;
use function Psl\Dict\map_with_key;
use function Psl\Env\get_var;
use function Psl\Env\remove_var;
use function Psl\Env\set_var;
use function Psl\SecureRandom\string;

final class EnvironmentVariablesTest extends TestCase
{
    private const RESET_ENVIRONMENT_VARIABLES = [
        'GIT_AUTHOR_EMAIL',
        'GIT_AUTHOR_NAME',
        'GITHUB_EVENT_PATH',
        'GITHUB_HOOK_SECRET',
        'GITHUB_ORGANISATION',
        'GITHUB_TOKEN',
        'GITHUB_WORKSPACE',
        'SIGNING_SECRET_KEY',
    ];

    /** @var array<string, ?string> */
    private array $originalValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalValues = associate(self::RESET_ENVIRONMENT_VARIABLES, map(
            self::RESET_ENVIRONMENT_VARIABLES,
            static fn (string $variable) => get_var($variable),
        ));
    }

    protected function tearDown(): void
    {
        $originalValues = $this->originalValues;

        map_with_key($originalValues, static function (string $key, ?string $value): void {
            if ($value === null) {
                remove_var($key);

                return;
            }

            set_var($key, $value);
        });

        parent::tearDown();
    }

    public function testReadsEnvironmentVariables(): void
    {
        $signingSecretKey   = 'signingSecretKey' . string(8);
        $signingSecretKeyId = SecretKeyId::fromBase16String('aabbccdd');
        $githubToken        = 'githubToken' . string(8);
        $githubOrganisation = 'githubOrganisation' . string(8);
        $gitAuthorName      = 'gitAuthorName' . string(8);
        $gitAuthorEmail     = 'gitAuthorEmail' . string(8);
        $githubEventPath    = 'githubEventPath' . string(8);
        $githubWorkspace    = 'githubWorkspace' . string(8);

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

        $variables = EnvironmentVariables::fromEnvironmentWithGpgKey($importKey);

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

        EnvironmentVariables::fromEnvironmentWithGpgKey($importKey);
    }
}
