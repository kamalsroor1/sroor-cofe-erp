// MOB-06 / MOB-03 / MOB-01: static checks on the Android shell configuration.
// Run: node --test scripts/capacitor/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const backendDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const read = (...parts) => readFileSync(path.join(backendDir, ...parts), 'utf8');
const capConfig = JSON.parse(read('capacitor.config.json'));
const appGradle = read('android', 'app', 'build.gradle');

test('MOB-06: the package id is com.ibraa.app everywhere', () => {
    assert.equal(capConfig.appId, 'com.ibraa.app');
    assert.match(appGradle, /namespace\s*=\s*"com\.ibraa\.app"/);
    assert.match(appGradle, /applicationId\s+"com\.ibraa\.app"/);
    assert.match(
        read('android', 'app', 'src', 'main', 'res', 'values', 'strings.xml'),
        /<string name="package_name">com\.ibraa\.app</
    );
    assert.ok(existsSync(path.join(backendDir, 'android/app/src/main/java/com/ibraa/app/MainActivity.java')));
    assert.ok(!existsSync(path.join(backendDir, 'android/app/src/main/java/com/sroor')));
    assert.ok(!JSON.stringify(capConfig).includes('cofe'));
});

test('MOB-06: the app opens the https hub and may only navigate inside ibraa.app', () => {
    assert.equal(capConfig.server.url, 'https://ibraa.app');
    assert.equal(capConfig.server.cleartext, false);
    assert.deepEqual(capConfig.server.allowNavigation, ['ibraa.app', '*.ibraa.app']);
});

test('MOB-03: play and direct flavors exist and only direct can self-update', () => {
    assert.match(appGradle, /productFlavors\s*\{[\s\S]*\bdirect\s*\{[\s\S]*\bplay\s*\{/);

    const mainManifest = read('android', 'app', 'src', 'main', 'AndroidManifest.xml');
    const directManifest = read('android', 'app', 'src', 'direct', 'AndroidManifest.xml');
    const playManifest = read('android', 'app', 'src', 'play', 'AndroidManifest.xml');
    const permission = /<uses-permission\s+android:name="android\.permission\.REQUEST_INSTALL_PACKAGES"\s*\/>/;

    assert.doesNotMatch(mainManifest, permission);
    assert.doesNotMatch(mainManifest, /androidx\.core\.content\.FileProvider/);
    assert.match(directManifest, permission);
    assert.match(directManifest, /androidx\.core\.content\.FileProvider/);
    assert.match(playManifest, /REQUEST_INSTALL_PACKAGES"\s*tools:node="remove"/);

    const javaDir = (flavor) => path.join(backendDir, 'android/app/src', flavor, 'java/com/ibraa/app');
    assert.ok(existsSync(path.join(javaDir('direct'), 'AppUpdaterPlugin.java')));
    assert.ok(!existsSync(path.join(javaDir('main'), 'AppUpdaterPlugin.java')));
    assert.ok(!existsSync(path.join(javaDir('play'), 'AppUpdaterPlugin.java')));
    assert.ok(existsSync(path.join(javaDir('play'), 'PlayStoreBuildPlugin.java')));
    assert.ok(existsSync(path.join(javaDir('direct'), 'FlavorPlugins.java')));
    assert.ok(existsSync(path.join(javaDir('play'), 'FlavorPlugins.java')));
    assert.match(read('android/app/src/main/java/com/ibraa/app/MainActivity.java'), /FlavorPlugins\.all\(\)/);
});
