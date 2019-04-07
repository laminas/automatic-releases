<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Environment;

use Doctrine\AutomaticReleases\Environment\Variables;
use PHPUnit\Framework\TestCase;
use function array_map;
use function array_walk;
use function getenv;
use function Safe\array_combine;
use function Safe\putenv;
use function uniqid;

final class VariablesTest extends TestCase
{
    private const RESET_ENVIRONMENT_VARIABLES = [
        'GITHUB_HOOK_SECRET',
        'GITHUB_TOKEN',
        'SIGNING_SECRET_KEY',
        'GITHUB_ORGANISATION',
        'GITHUB_ORGANISATION',
        'GIT_AUTHOR_NAME',
        'GIT_AUTHOR_EMAIL',
    ];

    /** @var array<string, string|false> */
    private $originalValues = [];

    protected function setUp() : void
    {
        parent::setUp();

        $this->originalValues = array_combine(
            self::RESET_ENVIRONMENT_VARIABLES,
            //            array_map('getenv', self::RESET_ENVIRONMENT_VARIABLES)
            array_map(static function (string $key) {
                return getenv($key);
            }, self::RESET_ENVIRONMENT_VARIABLES)
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
        $githubHookSecret   = uniqid('githubHookSecret', true);
        $signingSecretKey   = uniqid('signingSecretKey', true);
        $githubToken        = uniqid('githubToken', true);
        $githubOrganisation = uniqid('githubOrganisation', true);
        $gitAuthorName      = uniqid('gitAuthorName', true);
        $gitAuthorEmail     = uniqid('gitAuthorEmail', true);

        putenv('GITHUB_HOOK_SECRET=' . $githubHookSecret);
        putenv('GITHUB_TOKEN=' . $githubToken);
        putenv('SIGNING_SECRET_KEY=' . $signingSecretKey);
        putenv('GITHUB_ORGANISATION=' . $githubOrganisation);
        putenv('GIT_AUTHOR_NAME=' . $gitAuthorName);
        putenv('GIT_AUTHOR_EMAIL=' . $gitAuthorEmail);

        $variables = Variables::fromEnvironment();

        self::assertSame($githubHookSecret, $variables->githubHookSecret());
        self::assertSame($signingSecretKey, $variables->signingSecretKey());
        self::assertSame($githubToken, $variables->githubToken());
        self::assertSame($githubOrganisation, $variables->githubOrganisation());
        self::assertSame($gitAuthorName, $variables->gitAuthorName());
        self::assertSame($gitAuthorEmail, $variables->gitAuthorEmail());
    }
}
