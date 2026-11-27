// INV-460: CreatePurchaseView & child cards must stack cleanly on 390px viewports with zero horizontal overflow.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

const read = (path) => readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

test('CreatePurchaseView wraps outer container with min-w-0 and wraps submit actions', () => {
    const view = read('views/Purchases/CreatePurchaseView.vue');
    assert.match(view, /class="[^"]*\bmin-w-0\b[^"]*"/);
    assert.match(view, /class="[^"]*\bflex-wrap\b[^"]*"/);
});

test('CreatePurchaseSupplierCard uses single-column mobile grid and min-w-0 on all columns', () => {
    const card = read('Components/Purchases/CreatePurchaseSupplierCard.vue');
    assert.match(card, /grid-cols-1 md:grid-cols-3/);
    const minWCount = (card.match(/class="min-w-0"/g) || []).length;
    assert.ok(minWCount >= 3, 'Supplier, purchase date, and invoice ref grid cells must carry min-w-0');
});

test('CreatePurchaseItemsCard isolates desktop table at md: and guards mobile item cards against long-name overflow', () => {
    const card = read('Components/Purchases/CreatePurchaseItemsCard.vue');
    assert.match(card, /class="hidden md:block overflow-x-auto"/);
    assert.match(card, /class="block md:hidden space-y-3 min-w-0"/);
    assert.match(card, /class="[^"]*\bmin-w-0\b[^"]*"[\s\S]*<BaseSelect/);
    assert.match(card, /class="[^"]*\btruncate min-w-0\b[^"]*"/);
});

test('CreatePurchaseSummaryCard stacks paid/discount fields on mobile and protects ledger rows with min-w-0', () => {
    const card = read('Components/Purchases/CreatePurchaseSummaryCard.vue');
    assert.match(card, /grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6/);
    assert.match(card, /grid-cols-1 sm:grid-cols-2 gap-3 min-w-0/);
    assert.match(card, /class="[^"]*\bw-full min-w-0\b[^"]*"/);
    assert.match(card, /class="min-w-0 break-words"/);
});

test('BaseSelect applies min-w-0 and flex-1 truncate so long item/supplier labels truncate inside 390px cards', () => {
    const select = read('Components/Form/BaseSelect.vue');
    assert.match(select, /class="w-full min-w-0 flex flex-col gap-1\.5"/);
    assert.match(select, /class="truncate min-w-0 flex-1"/);
});
