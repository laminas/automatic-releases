<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Environment;

use Laminas\AutomaticReleases\Environment\EnvironmentVariables;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromString;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Laminas\AutomaticReleases\Test\Unit\TestCase;
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
        'TWITTER_ACCESS_TOKEN',
        'TWITTER_ACCESS_TOKEN_SECRET',
        'TWITTER_CONSUMER_API_KEY',
        'TWITTER_CONSUMER_API_SECRET',
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
        $signingSecretKey         = 'signingSecretKey' . string(8);
        $secretKeyId              = SecretKeyId::fromBase16String('aabbccdd');
        $githubToken              = 'githubToken' . string(8);
        $githubOrganisation       = 'githubOrganisation' . string(8);
        $gitAuthorName            = 'gitAuthorName' . string(8);
        $gitAuthorEmail           = 'gitAuthorEmail' . string(8);
        $githubEventPath          = 'githubEventPath' . string(8);
        $githubWorkspace          = 'githubWorkspace' . string(8);
        $twitterAccessToken       = 'twitterAccessToken' . string(8);
        $twitterAccessTokenSecret = 'twitterAccessTokenSecret' . string(8);
        $twitterConsumerApiKey    = 'twitterConsumerApiKey' . string(8);
        $twitterConsumerApiSecret = 'twitterConsumerApiSecret' . string(8);

        set_var('GITHUB_TOKEN', $githubToken);
        set_var('SIGNING_SECRET_KEY', $signingSecretKey);
        set_var('GITHUB_ORGANISATION', $githubOrganisation);
        set_var('GIT_AUTHOR_NAME', $gitAuthorName);
        set_var('GIT_AUTHOR_EMAIL', $gitAuthorEmail);
        set_var('GITHUB_EVENT_PATH', $githubEventPath);
        set_var('GITHUB_WORKSPACE', $githubWorkspace);
        set_var('TWITTER_ACCESS_TOKEN', $twitterAccessToken);
        set_var('TWITTER_ACCESS_TOKEN_SECRET', $twitterAccessTokenSecret);
        set_var('TWITTER_CONSUMER_API_KEY', $twitterConsumerApiKey);
        set_var('TWITTER_CONSUMER_API_SECRET', $twitterConsumerApiSecret);

        $importKey = $this->createMock(ImportGpgKeyFromString::class);

        $importKey->method('__invoke')
            ->with($signingSecretKey)
            ->willReturn($secretKeyId);

        $environment = EnvironmentVariables::fromEnvironmentWithGpgKey($importKey);

        self::assertEquals($secretKeyId, $environment->secretKeyId());
        self::assertSame($githubToken, $environment->githubToken());
        self::assertSame($gitAuthorName, $environment->gitAuthorName());
        self::assertSame($gitAuthorEmail, $environment->gitAuthorEmail());
        self::assertSame($githubEventPath, $environment->githubEventPath());
        self::assertSame($githubWorkspace, $environment->githubWorkspacePath());
        self::assertSame($twitterAccessToken, $environment->twitterAccessToken());
        self::assertSame($twitterAccessTokenSecret, $environment->twitterAccessTokenSecret());
        self::assertSame($twitterConsumerApiKey, $environment->twitterConsumerApiKey());
        self::assertSame($twitterConsumerApiSecret, $environment->twitterConsumerApiSecret());
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
