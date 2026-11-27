// BIL-035 (#308): direct navigation to /activity-logs on Starter shows FeatureGate upsell
// ("هذه الميزة غير متاحة في باقتك") cleanly without mounting ActivityLogsView or triggering 403s.
// Run from backend/: node --test tests/js/activity-logs-feature-gate.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { hasEntitlementFeature } from '../../resources/js/helpers/planFeatures.js';

const backendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const readBackendFile = (rel) => fs.readFileSync(path.join(backendRoot, rel), 'utf8');

test('hasEntitlementFeature locks audit.logs on Starter and unlocks it on Growth', () => {
    const starterEntitlements = {
        enforced: true,
        features: ['pos.access', 'reports.basic', 'transfers.manage', 'reports.export'],
    };
    const growthEntitlements = {
        enforced: true,
        features: ['pos.access', 'reports.basic', 'transfers.manage', 'reports.advanced', 'audit.logs'],
    };

    assert.equal(hasEntitlementFeature(starterEntitlements, 'audit.logs'), false);
    assert.equal(hasEntitlementFeature(growthEntitlements, 'audit.logs'), true);
    assert.equal(hasEntitlementFeature(starterEntitlements, ['reports.advanced', 'audit.logs']), false);
    assert.equal(hasEntitlementFeature(growthEntitlements, ['reports.advanced', 'audit.logs']), true);
});

test('hasEntitlementFeature allows routes with no feature or unenforced/unloaded context', () => {
    assert.equal(hasEntitlementFeature(null, 'audit.logs'), true);
    assert.equal(hasEntitlementFeature({ enforced: false, features: [] }, 'audit.logs'), true);
    assert.equal(hasEntitlementFeature({ enforced: true, features: [] }, null), true);
    assert.equal(hasEntitlementFeature({ enforced: true, features: [] }, undefined), true);
});

test('router /activity-logs loads bootstrap context before rendering and delegates feature gate to App.vue', () => {
    const routerSrc = readBackendFile('resources/js/router/index.js');
    const appSrc = readBackendFile('resources/js/App.vue');
    const storeSrc = readBackendFile('resources/js/stores/appConfig.js');
    const featureGateSrc = readBackendFile('resources/js/Components/FeatureGate.vue');
    const arLang = readBackendFile('lang/ar/subscription.php');
    const enLang = readBackendFile('lang/en/subscription.php');

    // 1. /activity-logs declares feature: 'audit.logs' and awaits bootstrap context in tenantGuard.
    assert.match(routerSrc, /path:\s*'\/activity-logs'[\s\S]*?feature:\s*'audit\.logs'/);
    assert.match(routerSrc, /await appConfigStore\.fetchBootstrapContext\(\)/);

    // 2. appConfig store uses hasEntitlementFeature and App.vue renders FeatureGate page without mounting the view.
    assert.match(
        storeSrc,
        /hasFeature:\s*\(state\)\s*=>\s*\(keyOrKeys\)\s*=>\s*hasEntitlementFeature\(state\.entitlements,\s*keyOrKeys\)/
    );
    assert.match(appSrc, /v-if="appConfigStore\.hasFeature\(currentRoute\.meta\.feature\)"/);
    assert.match(appSrc, /<FeatureGate[\s\S]*?:feature="currentRoute\.meta\.feature"[\s\S]*?page/);

    // 3. FeatureGate renders the exact Arabic upsell message and both locales contain the keys.
    assert.match(featureGateSrc, /\$t\('subscription\.upsell\.title'\)/);
    assert.match(arLang, /'title'\s*=>\s*'هذه الميزة غير متاحة في باقتك'/);
    assert.match(enLang, /'title'\s*=>\s*'This feature is not in your plan'/);
});
