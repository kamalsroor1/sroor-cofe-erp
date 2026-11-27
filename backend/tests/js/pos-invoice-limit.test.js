// SAL-091 (#361): POS invoice limit banner & sell-button upgrade prompt when max_invoices_per_month is reached.
// Run from backend/: node --test tests/js/pos-invoice-limit.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    isLimitReachedError,
    isPosInvoiceBannerVisible,
    isPosInvoiceLimitNear,
    isPosInvoiceLimitReached,
    limitResourceOf,
} from '../../resources/js/helpers/planLimit.js';

const backendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const readBackendFile = (rel) => fs.readFileSync(path.join(backendRoot, rel), 'utf8');

test('POS invoice limit state detects reached cap (including zero cap) and hides near warning', () => {
    const atCap = { limit: 150, used: 150, remaining: 0, percent: 100, reached: true, resets_at: '2026-12-01' };
    assert.equal(isPosInvoiceLimitReached(atCap), true);
    assert.equal(isPosInvoiceLimitNear(atCap), false);
    assert.equal(isPosInvoiceBannerVisible(atCap, true, false), true);

    const zeroCap = { limit: 0, used: 0, remaining: 0, percent: 100, reached: true, resets_at: '2026-12-01' };
    assert.equal(isPosInvoiceLimitReached(zeroCap), true);
    assert.equal(isPosInvoiceLimitNear(zeroCap), false);
    assert.equal(isPosInvoiceBannerVisible(zeroCap, true, false), true);
});

test('POS invoice limit shows near-limit warning at >= 80% and stays hidden below 80% or unlimited', () => {
    const near = { limit: 150, used: 120, remaining: 30, percent: 80, reached: false };
    assert.equal(isPosInvoiceLimitReached(near), false);
    assert.equal(isPosInvoiceLimitNear(near), true);
    assert.equal(isPosInvoiceBannerVisible(near, false, true), true);

    const healthy = { limit: 150, used: 50, remaining: 100, percent: 33, reached: false };
    assert.equal(isPosInvoiceLimitReached(healthy), false);
    assert.equal(isPosInvoiceLimitNear(healthy), false);
    assert.equal(isPosInvoiceBannerVisible(healthy, false, false), false);

    const unlimited = { limit: null, used: 500, remaining: null, percent: null, reached: false };
    assert.equal(isPosInvoiceLimitReached(unlimited), false);
    assert.equal(isPosInvoiceLimitNear(unlimited), false);
    assert.equal(isPosInvoiceBannerVisible(null, false, false), false);
});

test('server 403 subscription.limit_reached for invoices_month is recognized by planLimit helper', () => {
    const err = {
        response: {
            status: 403,
            data: { error_code: 'subscription.limit_reached', details: { limit: 'invoices_month', max: 150 } },
        },
    };
    assert.equal(isLimitReachedError(err), true);
    assert.equal(limitResourceOf(err), 'invoices_month');
});

test('PosView, POSInvoiceLimitBanner, usePosInvoiceLimit and usePosSubmit wire banner + sell upgrade prompt', () => {
    const posViewSrc = readBackendFile('resources/js/views/POS/PosView.vue');
    const bannerSrc = readBackendFile('resources/js/Components/POS/POSInvoiceLimitBanner.vue');
    const limitComposableSrc = readBackendFile('resources/js/Composables/POS/usePosInvoiceLimit.js');
    const submitComposableSrc = readBackendFile('resources/js/Composables/POS/usePosSubmit.js');
    const checkoutPanelSrc = readBackendFile('resources/js/Components/POS/POSCheckoutPanel.vue');
    const arLang = readBackendFile('lang/ar/subscription.php');
    const enLang = readBackendFile('lang/en/subscription.php');

    // Banner wired into PosView and driven by shared visibility helper.
    assert.match(
        posViewSrc,
        /<POSInvoiceLimitBanner[\s\S]*?:reached="invoiceLimit\.reached\.value"[\s\S]*?:near-limit="invoiceLimit\.nearLimit\.value"/
    );
    assert.match(bannerSrc, /isPosInvoiceBannerVisible\(props\.status,\s*props\.reached,\s*props\.nearLimit\)/);
    assert.match(bannerSrc, /data-testid="pos-invoice-limit-banner"/);

    // Sell button triggers submit even when limit reached so pre-checkout guard displays the upgrade prompt.
    assert.match(
        checkoutPanelSrc,
        /data-testid="pos-checkout-submit"[\s\S]*?:disabled="cartEmpty \|\| isSubmitting \|\| offline"/
    );
    assert.match(submitComposableSrc, /if \(invoiceLimit && !\(await invoiceLimit\.allowCheckout\(\)\)\)/);
    assert.match(limitComposableSrc, /showUpgradeAlert\(\{\s*titleKey:\s*'subscription\.alerts\.limit_reached_title'/);

    // Required i18n keys exist in both Arabic and English dictionaries.
    for (const key of ['reached_title', 'reached_hint', 'near_title', 'near_hint', 'blocked_text']) {
        assert.match(arLang, new RegExp(`'${key}'\\s*=>`));
        assert.match(enLang, new RegExp(`'${key}'\\s*=>`));
    }
    assert.match(arLang, /'limit_reached_title'\s*=>/);
    assert.match(enLang, /'limit_reached_title'\s*=>/);
});
