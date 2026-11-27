// ADM-001: Vue router isolation on tenant vs central hosts.
// On a tenant host (<meta name="app-context" content="central"> is absent), Vue Router must NOT
// register any /super-admin* routes and any navigation to /super-admin/login must fall through
// to the catch-all redirect to '/'.
// Run from backend/: node --test tests/js/router-isolation.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { createMemoryHistory, createRouter } from 'vue-router';

const readSource = (relPath) =>
    readFileSync(fileURLToPath(new URL(`../../resources/js/${relPath}`, import.meta.url)), 'utf8');

function withFakeDocument(metaContent, fn) {
    const previousDocument = globalThis.document;
    globalThis.document =
        metaContent === undefined
            ? { querySelector: () => null }
            : {
                  querySelector: (selector) =>
                      selector === 'meta[name="app-context"]' ? { getAttribute: () => metaContent } : null,
              };
    try {
        return fn();
    } finally {
        globalThis.document = previousDocument;
    }
}

test('isCentralAppContext returns true only when <meta name="app-context" content="central"> is present', () => {
    const centralApiSource = readSource('Services/centralApi.js');
    const fnMatch = /export function isCentralAppContext\(\)\s*\{([\s\S]*?)\n\}/.exec(centralApiSource);
    assert.ok(fnMatch, 'isCentralAppContext() must be exported by Services/centralApi.js');

    const isCentralAppContext = new Function(fnMatch[1]);

    assert.equal(
        withFakeDocument(undefined, () => isCentralAppContext()),
        false
    );
    assert.equal(
        withFakeDocument('tenant', () => isCentralAppContext()),
        false
    );
    assert.equal(
        withFakeDocument('central', () => isCentralAppContext()),
        true
    );
});

test('tenantRoutes excludes all super-admin routes and falls through /super-admin/login to not-found', async () => {
    const routerSource = readSource('router/index.js');

    const tenantMatch = /const tenantRoutes = \[([\s\S]*?)\n\];/.exec(routerSource);
    assert.ok(tenantMatch, 'tenantRoutes array must exist in router/index.js');
    const tenantBlock = tenantMatch[1];

    assert.ok(!tenantBlock.includes('/super-admin'), 'tenantRoutes must never register any /super-admin path');
    assert.ok(!tenantBlock.includes('super_admin'), 'tenantRoutes must never register any super_admin route name');
    assert.match(
        tenantBlock,
        /path:\s*'\/:pathMatch\(\.\*\)\*'[\s\S]*?name:\s*'not-found'[\s\S]*?NotFoundView\.vue/,
        'tenantRoutes must include a catch-all not-found view'
    );

    const centralMatch = /const centralRoutes = \[([\s\S]*?)\n\];/.exec(routerSource);
    assert.ok(centralMatch, 'centralRoutes array must exist in router/index.js');
    assert.match(centralMatch[1], /'\/super-admin\/login'/);
    assert.match(routerSource, /routes:\s*CENTRAL_MODE\s*\?\s*centralRoutes\s*:\s*tenantRoutes/);

    // Exercise actual vue-router resolution with the tenant route table topology.
    const Stub = { template: '<div />' };
    const tenantRouter = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'dashboard', component: Stub },
            { path: '/login', name: 'login', component: Stub },
            { path: '/:pathMatch(.*)*', name: 'not-found', component: Stub },
        ],
    });

    await tenantRouter.push('/super-admin/login');
    await tenantRouter.isReady();
    assert.equal(tenantRouter.currentRoute.value.name, 'not-found');
    assert.equal(tenantRouter.hasRoute('super_admin.login'), false);

    await tenantRouter.push('/super-admin/dashboard');
    assert.equal(tenantRouter.currentRoute.value.name, 'not-found');
});
