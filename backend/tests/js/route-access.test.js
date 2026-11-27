// MGT-150: route permission guard & /expenses access gating for roles without expenses.manage.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';
import {
    canAccessMatchedRoute,
    deniedRouteRedirect,
    hasRoutePermission,
} from '../../resources/js/helpers/routeAccess.js';
import {
    flushPendingRouteNotice,
    hasPendingRouteNotice,
    isAppReady,
    markAppReady,
    queueRouteNotice,
    resetPendingRouteNotice,
} from '../../resources/js/helpers/pendingRouteNotice.js';

const makeAuth = (roles = [], permissions = []) => ({
    hasPermission: (required) => {
        if (roles.includes('admin')) return true;
        if (Array.isArray(required)) return required.some((p) => permissions.includes(p));
        return permissions.includes(required);
    },
    hasRole: (role) => roles.includes(role),
});

const allFeatures = { hasFeature: () => true };

test('router defines /expenses with expenses.manage permission and feature gates', () => {
    const routerSource = readFileSync(new URL('../../resources/js/router/index.js', import.meta.url), 'utf8');
    const expensesBlock = routerSource.match(/path:\s*'\/expenses'[\s\S]*?meta:\s*\{[\s\S]*?\}/);
    assert.ok(expensesBlock, '/expenses route block exists in router/index.js');
    assert.match(expensesBlock[0], /name:\s*'expenses\.index'/);
    assert.match(expensesBlock[0], /permission:\s*'expenses\.manage'/);
    assert.match(expensesBlock[0], /feature:\s*'expenses\.manage'/);
    assert.match(routerSource, /deniedRouteRedirect\(to\.meta,\s*authStore\)/);
});

test('cashier without expenses.manage is denied /expenses, notified with common.no_access, and redirected home', () => {
    const expensesMeta = {
        titleKey: 'nav.page_titles.expenses_index',
        requiresAuth: true,
        feature: 'expenses.manage',
        permission: 'expenses.manage',
    };
    const cashier = makeAuth(['cashier'], ['pos.access', 'daily_journal.view', 'invoices.view']);

    assert.equal(hasRoutePermission(expensesMeta, cashier), false);
    assert.equal(canAccessMatchedRoute([{ meta: expensesMeta }], cashier, allFeatures), false);
    assert.deepEqual(deniedRouteRedirect(expensesMeta, cashier), {
        messageKey: 'common.no_access',
        redirect: { name: 'dashboard' },
    });

    for (const locale of ['ar', 'en']) {
        const dict = JSON.parse(
            readFileSync(
                new URL(`../../resources/js/helpers/defaultTranslations.${locale}.json`, import.meta.url),
                'utf8'
            )
        );
        assert.ok(typeof dict?.common?.no_access === 'string' && dict.common.no_access.length > 0);
    }
});

test('accountant with expenses.manage and admin are allowed to open /expenses', () => {
    const expensesMeta = {
        titleKey: 'nav.page_titles.expenses_index',
        requiresAuth: true,
        feature: 'expenses.manage',
        permission: 'expenses.manage',
    };
    const accountant = makeAuth(['accountant'], ['expenses.manage', 'daily_journal.view', 'reports.view']);
    const admin = makeAuth(['admin'], []);

    assert.equal(hasRoutePermission(expensesMeta, accountant), true);
    assert.equal(canAccessMatchedRoute([{ meta: expensesMeta }], accountant, allFeatures), true);
    assert.equal(deniedRouteRedirect(expensesMeta, accountant), null);

    assert.equal(hasRoutePermission(expensesMeta, admin), true);
    assert.equal(canAccessMatchedRoute([{ meta: expensesMeta }], admin, allFeatures), true);
    assert.equal(deniedRouteRedirect(expensesMeta, admin), null);
});

// GEN-106: direct URL entry to /users, /settings, or /billing by a cashier must surface common.no_access AFTER boot splash hides.
test('GEN-106: cashier is denied /users, /settings, and /billing and pending notice waits until boot splash hides on cold load', () => {
    const routerSource = readFileSync(new URL('../../resources/js/router/index.js', import.meta.url), 'utf8');
    const appSource = readFileSync(new URL('../../resources/js/App.vue', import.meta.url), 'utf8');
    const splashSource = readFileSync(
        new URL('../../resources/js/Components/Common/SystemBootSplash.vue', import.meta.url),
        'utf8'
    );

    // Guard queues notice instead of firing during boot; afterEach + App.vue splash hidden flush it.
    assert.match(routerSource, /queueRouteNotice\(denied\.messageKey\)/);
    assert.match(routerSource, /router\.afterEach\(\(\) => \{\s*if \(isAppReady\(\)\)/);
    assert.match(appSource, /<SystemBootSplash :show="isBooting" @hidden="revealAppReady" \/>/);
    assert.match(appSource, /markAppReady\(\(key\) => notifyError\(trans\(key\)\)\)/);
    assert.match(splashSource, /@after-leave="emit\('hidden'\)"/);

    const cashier = makeAuth(['cashier'], ['pos.access', 'daily_journal.view', 'invoices.view']);
    const restrictedRoutes = [
        { path: '/users', permission: 'roles.manage' },
        { path: '/settings', permission: 'settings.manage' },
        { path: '/billing', permission: 'billing.manage' },
    ];

    for (const route of restrictedRoutes) {
        resetPendingRouteNotice();
        const denied = deniedRouteRedirect({ requiresAuth: true, permission: route.permission }, cashier);
        assert.deepEqual(denied, {
            messageKey: 'common.no_access',
            redirect: { name: 'dashboard' },
        });

        // Cold load: router.beforeEach queues the notice, router.afterEach sees !isAppReady() and keeps it queued.
        queueRouteNotice(denied.messageKey);
        assert.equal(isAppReady(), false);
        assert.equal(hasPendingRouteNotice(), true);

        const shown = [];
        if (isAppReady()) {
            flushPendingRouteNotice((key) => shown.push(key));
        }
        assert.deepEqual(shown, [], `Toast must wait for boot splash on cold load to ${route.path}`);

        // SystemBootSplash finishes hiding -> App.vue calls markAppReady -> toast fires once.
        markAppReady((key) => shown.push(key));
        assert.deepEqual(shown, ['common.no_access']);
        assert.equal(hasPendingRouteNotice(), false);

        // Second call (e.g. fallback timer) is idempotent and does not duplicate toast.
        markAppReady((key) => shown.push(key));
        assert.deepEqual(shown, ['common.no_access']);

        // Subsequent in-app navigation after boot flushes immediately in afterEach.
        queueRouteNotice(denied.messageKey);
        if (isAppReady()) {
            flushPendingRouteNotice((key) => shown.push(key));
        }
        assert.deepEqual(shown, ['common.no_access', 'common.no_access']);
    }

    resetPendingRouteNotice();
});
