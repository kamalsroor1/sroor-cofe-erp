// ROLE-01: default landing page by role, and the shared launcher-tile access filter.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { defaultLandingPath, landingPathFor } from '../../resources/js/helpers/landingPath.js';
import { filterAccessible, requiredPaths } from '../../resources/js/helpers/accessFilter.js';

const can =
    (...granted) =>
    (permission) =>
        granted.includes(permission);

test('a till-only user (pos.access, no reports.view) lands on the POS', () => {
    assert.equal(defaultLandingPath({ can: can('pos.access', 'returns.manage') }), '/pos');
});

test('anyone who can read reports keeps the dashboard', () => {
    assert.equal(defaultLandingPath({ can: can('pos.access', 'reports.view') }), '/');
    assert.equal(defaultLandingPath({ can: can('reports.view') }), '/');
});

test('a user without pos.access keeps the dashboard', () => {
    assert.equal(defaultLandingPath({ can: can('invoices.view') }), '/');
    assert.equal(defaultLandingPath({}), '/');
});

test('a plan without the POS feature keeps the dashboard', () => {
    const access = { can: can('pos.access'), hasFeature: () => false };
    assert.equal(defaultLandingPath(access), '/');
});

test('landingPathFor reads the auth and app config stores (admin passes every check)', () => {
    const admin = { hasPermission: () => true };
    const config = { hasFeature: () => true };
    assert.equal(landingPathFor(admin, config), '/');
    const cashier = { hasPermission: (p) => p === 'pos.access' };
    assert.equal(landingPathFor(cashier, config), '/pos');
});

test('filterAccessible drops tiles whose target or dependency is not allowed', () => {
    const allowed = new Set(['/pos', '/returns', '/returns/create', '/customers']);
    const canAccessPath = (path) => allowed.has(path);
    const tiles = [
        { id: 'pos', to: '/pos' },
        { id: 'sale_returns', to: '/returns/create' },
        { id: 'purchases', to: '/purchases/create' },
        { id: 'purchase_returns', to: '/returns', also: ['/purchases'] },
        { id: 'categories', to: '/categories' },
        { id: 'store_stocks', to: '/stores/stocks' },
        { id: 'customers', to: '/customers' },
    ];
    assert.deepEqual(
        filterAccessible(tiles, canAccessPath).map((tile) => tile.id),
        ['pos', 'sale_returns', 'customers']
    );
});

test('requiredPaths lists the target first and ignores missing values', () => {
    assert.deepEqual(requiredPaths({ to: '/a', also: ['/b'] }), ['/a', '/b']);
    assert.deepEqual(requiredPaths({ to: '/a' }), ['/a']);
});
