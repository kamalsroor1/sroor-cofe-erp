// Trello #239 (demo trial 2026-11-26): the customers filter tabs sent `balance_type`, but
// CustomerController reads `debt_status`, so the creditor/debtor/zero tabs listed everyone.
// Run: node --test tests/js/customers-debt-filter.test.js   (part of `npm run test:js`)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';

const source = readFileSync(new URL('../../resources/js/Composables/useCustomers.js', import.meta.url), 'utf8');

test('the customers list sends the debt filter as debt_status', () => {
    assert.match(source, /debt_status:\s*debtStatus\.value/);
    assert.doesNotMatch(source, /balance_type/);
});
