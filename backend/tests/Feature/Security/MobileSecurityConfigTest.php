<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use SimpleXMLElement;
use Tests\TestCase;

/**
 * MOB-02 & MOB-07: Mobile & Android Security Hardening.
 *
 * Verifies that:
 * 1. targetSdkVersion in variables.gradle meets Google Play requirements (>= 35).
 * 2. Cleartext HTTP traffic is disabled in capacitor.config.json.
 * 3. android:allowBackup="false" is enforced in AndroidManifest.xml to prevent token/credential exfiltration.
 * 4. file_paths.xml does not expose root external storage (<external-path path=".">) via FileProvider.
 */
final class MobileSecurityConfigTest extends TestCase
{
    private string $androidDir;

    private string $capacitorConfigPath;

    private string $androidAssetsCapacitorConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $baseDir = dirname(__DIR__, 3);
        $this->androidDir = $baseDir.'/android';
        $this->capacitorConfigPath = $baseDir.'/capacitor.config.json';
        $this->androidAssetsCapacitorConfigPath = $this->androidDir.'/app/src/main/assets/capacitor.config.json';
    }

    public function test_android_target_sdk_version_meets_google_play_requirement(): void
    {
        $variablesGradlePath = $this->androidDir.'/variables.gradle';
        $this->assertFileExists($variablesGradlePath);

        $content = (string) file_get_contents($variablesGradlePath);

        $this->assertMatchesRegularExpression(
            '/targetSdkVersion\s*=\s*(\d+)/',
            $content,
            'variables.gradle must specify targetSdkVersion'
        );

        preg_match('/targetSdkVersion\s*=\s*(\d+)/', $content, $targetMatches);
        $targetSdkVersion = (int) ($targetMatches[1] ?? 0);

        // Google Play requirement for Android 15 is API 35+
        $this->assertGreaterThanOrEqual(
            35,
            $targetSdkVersion,
            "targetSdkVersion ({$targetSdkVersion}) must be at least 35 to comply with Google Play requirements."
        );

        preg_match('/compileSdkVersion\s*=\s*(\d+)/', $content, $compileMatches);
        $compileSdkVersion = (int) ($compileMatches[1] ?? 0);

        $this->assertGreaterThanOrEqual(
            $targetSdkVersion,
            $compileSdkVersion,
            'compileSdkVersion must be greater than or equal to targetSdkVersion'
        );
    }

    public function test_capacitor_config_disables_cleartext_traffic(): void
    {
        $this->assertFileExists($this->capacitorConfigPath);
        $config = json_decode((string) file_get_contents($this->capacitorConfigPath), true);
        $this->assertIsArray($config);

        $this->assertArrayHasKey('server', $config);
        $this->assertFalse(
            $config['server']['cleartext'] ?? true,
            'capacitor.config.json must set server.cleartext to false'
        );

        if (file_exists($this->androidAssetsCapacitorConfigPath)) {
            $androidConfig = json_decode((string) file_get_contents($this->androidAssetsCapacitorConfigPath), true);
            $this->assertIsArray($androidConfig);
            $this->assertFalse(
                $androidConfig['server']['cleartext'] ?? true,
                'android assets capacitor.config.json must set server.cleartext to false'
            );
        }
    }

    public function test_android_manifest_disables_allow_backup(): void
    {
        $manifestPath = $this->androidDir.'/app/src/main/AndroidManifest.xml';
        $this->assertFileExists($manifestPath);

        $manifestContent = (string) file_get_contents($manifestPath);
        $xml = new SimpleXMLElement($manifestContent);

        $androidNamespace = 'http://schemas.android.com/apk/res/android';
        $application = $xml->application;
        $this->assertNotNull($application);

        $attributes = $application->attributes($androidNamespace);
        $allowBackup = (string) ($attributes['allowBackup'] ?? '');

        $this->assertSame(
            'false',
            $allowBackup,
            'AndroidManifest.xml must set android:allowBackup="false" to prevent data exfiltration'
        );
    }

    public function test_file_paths_does_not_expose_root_external_storage(): void
    {
        // MOB-03: the FileProvider (and its file_paths.xml) lives in the flavor source sets.
        $files = glob($this->androidDir.'/app/src/*/res/xml/file_paths.xml') ?: [];
        $this->assertNotEmpty($files, 'at least one flavor must declare file_paths.xml');

        foreach ($files as $filePathsXmlPath) {
            $this->assertFilePathsAreScoped($filePathsXmlPath);
        }
    }

    private function assertFilePathsAreScoped(string $filePathsXmlPath): void
    {
        $xmlContent = (string) file_get_contents($filePathsXmlPath);
        $xml = new SimpleXMLElement($xmlContent);

        // Disallow dangerous whole external-path "." exposure
        foreach ($xml->xpath('//external-path') ?: [] as $element) {
            $path = (string) ($element['path'] ?? '');
            $this->assertNotSame(
                '.',
                $path,
                'file_paths.xml must not expose the entire external storage root (<external-path path="." />)'
            );
        }

        // Verify all defined path tags are standard safe/scoped types
        $allowedTags = ['cache-path', 'external-files-path', 'external-cache-path', 'files-path'];
        foreach ($xml->children() as $tag => $child) {
            $this->assertContains(
                $tag,
                $allowedTags,
                "file_paths.xml should only expose application-scoped paths, found: <{$tag}>"
            );
        }
    }
}
