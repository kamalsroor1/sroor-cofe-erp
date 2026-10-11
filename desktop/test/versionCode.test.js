'use strict';

// APP-7: the desktop build's version_code is SemverCode(app.getVersion()), identical to the
// server's App\Support\AppReleases\SemverCode (the updater used to send '0').

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const { semverCode, versionFromCode } = require(path.join(__dirname, '..', 'src', 'updater', 'versionCode.js'));

test('MAJOR.MINOR.PATCH maps to major*1e6 + minor*1e3 + patch', () => {
    assert.equal(semverCode('1.2.3'), 1002003);
    assert.equal(semverCode('1.0.0'), 1000000);
    assert.equal(semverCode('0.0.1'), 1);
    assert.equal(semverCode('999.999.999'), 999999999);
    assert.equal(semverCode(' 2.10.0 '), 2010000);
});

test('ordering follows semantic versions (1.10.0 > 1.9.9)', () => {
    assert.ok(semverCode('1.10.0') > semverCode('1.9.9'));
    assert.ok(semverCode('2.0.0') > semverCode('1.999.999'));
});

test('anything but MAJOR.MINOR.PATCH (0..999 each) is rejected', () => {
    for (const bad of ['1.2', '1.2.3.4', '1.2.3-beta', '1000.0.0', 'v1.2.3', '', null, undefined, 123]) {
        assert.equal(semverCode(bad), null, `${String(bad)} must be rejected`);
    }
});

test('versionFromCode is the inverse', () => {
    assert.equal(versionFromCode(1002003), '1.2.3');
    assert.equal(versionFromCode(semverCode('12.0.7')), '12.0.7');
    assert.equal(versionFromCode(-1), null);
    assert.equal(versionFromCode(1.5), null);
});
