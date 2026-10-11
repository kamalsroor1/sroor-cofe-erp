<?php

declare(strict_types=1);

namespace Tests\Unit\Support\AppReleases;

use PHPUnit\Framework\TestCase;

/**
 * N2: CreateAppVersionAction throws translated messages only (lang app_releases.*), never a
 * hardcoded literal, and the key it uses exists in both locales.
 */
final class CreateAppVersionMessagesTest extends TestCase
{
    public function test_no_hardcoded_exception_messages_remain(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).'/app/Actions/AppVersions/CreateAppVersionAction.php');

        $this->assertDoesNotMatchRegularExpression('/new \w*Exception\(\s*[\'"]/', $source);
        $this->assertStringContainsString("__('app_releases.binary_write_failed')", $source);

        foreach (['ar', 'en'] as $locale) {
            $lang = require dirname(__DIR__, 4).'/lang/'.$locale.'/app_releases.php';
            $this->assertIsString($lang['binary_write_failed'] ?? null, $locale);
        }
    }
}
