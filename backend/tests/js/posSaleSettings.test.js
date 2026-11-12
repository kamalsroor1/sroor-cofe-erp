// SET-01: POS sale settings helpers (what the checkout shows, how many receipts print, settings form <-> API).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    DEFAULT_SALE_SETTINGS,
    presetsWithinCap,
    receiptCopiesToPrint,
    usePosSaleSettings,
} from '../../resources/js/Composables/POS/usePosSaleSettings.js';
import { cleanNumber, saleSettingsFromApi, validateSaleSettings } from '../../resources/js/helpers/posSaleSettingsForm.js';

test('defaults equal the behaviour before the setting existed', () => {
    assert.deepEqual(DEFAULT_SALE_SETTINGS.cash_denominations, ['50', '100', '200', '500']);
    assert.deepEqual(DEFAULT_SALE_SETTINGS.discount_presets, ['0', '5', '10']);
    assert.equal(receiptCopiesToPrint(DEFAULT_SALE_SETTINGS, false), 0);
    assert.equal(receiptCopiesToPrint(DEFAULT_SALE_SETTINGS, true), 1);
});

test('the checkout shows the methods, buttons and presets from the bootstrap', () => {
    const { applySaleSettings, enabledMethods, isMethodEnabled, cashDenominations, saleSettings } = usePosSaleSettings();
    applySaleSettings({ payment_methods: ['cash', 'visa'], cash_denominations: ['20', '50', '100'], discount_presets: ['0', '3', '15'] });
    assert.deepEqual(enabledMethods.value, ['cash', 'visa']);
    assert.equal(isMethodEnabled('instapay'), false);
    assert.equal(isMethodEnabled('visa'), true);
    assert.deepEqual(cashDenominations.value, [20, 50, 100]);
    assert.deepEqual(presetsWithinCap(saleSettings.value.discount_presets, '10.000'), [0, 3]);
    applySaleSettings(null);
    assert.deepEqual(enabledMethods.value, DEFAULT_SALE_SETTINGS.payment_methods);
});

test('auto print prints one receipt per copy, a manual save-and-print uses the copies too', () => {
    assert.equal(receiptCopiesToPrint({ auto_print: 'thermal', receipt_copies: 2 }, false), 2);
    assert.equal(receiptCopiesToPrint({ auto_print: 'none', receipt_copies: 2 }, true), 2);
    assert.equal(receiptCopiesToPrint({ auto_print: 'none', receipt_copies: 2 }, false), 0);
    assert.equal(receiptCopiesToPrint({ auto_print: 'thermal', receipt_copies: 9 }, false), 1);
});

test('settings form: CSV from the API, defaults when never saved, cash always on', () => {
    assert.deepEqual(saleSettingsFromApi({}).pos_cash_denominations, ['50', '100', '200', '500']);
    const saved = saleSettingsFromApi({
        pos_enabled_payment_methods: 'instapay,visa',
        pos_cash_denominations: '20,50',
        pos_receipt_copies: '2',
        pos_auto_print: 'thermal',
    });
    assert.deepEqual(saved.pos_enabled_payment_methods, ['cash', 'instapay', 'visa']);
    assert.deepEqual(saved.pos_cash_denominations, ['20', '50']);
    assert.equal(saved.pos_receipt_copies, 2);
    assert.equal(saved.pos_auto_print, 'thermal');
});

test('settings form: number cleaning and list limits', () => {
    assert.equal(cleanNumber('20', { min: 1 }), '20');
    assert.equal(cleanNumber('0', { min: 1 }), null);
    assert.equal(cleanNumber('-5', { min: 1 }), null);
    assert.equal(cleanNumber('0', { min: 0, max: 100 }), '0');
    assert.equal(cleanNumber('101', { min: 0, max: 100 }), null);
    assert.deepEqual(validateSaleSettings({ pos_cash_denominations: ['1', '2', '3', '4', '5', '6', '7'], pos_discount_presets: ['5'] }), {
        pos_cash_denominations: ['settings.pos_denominations_invalid'],
    });
});
