// GEN-013: long form modals must pin Save/Cancel in AppModal #footer while scrolling only the body.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

const read = (path) => readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

test('AppModal constrains height with dvh and pins header & footer around a min-h-0 scrollable body', () => {
    const modal = read('Components/Common/AppModal.vue');
    assert.match(modal, /max-h-\[90dvh\]/);
    assert.match(modal, /flex flex-col/);
    assert.match(modal, /flex-1 min-h-0 overflow-y-auto/);
    assert.match(modal, /<div v-if="\$slots\.footer" class="[^"]*shrink-0[^"]*">/);
});

const LONG_FORM_MODALS = [
    { file: 'Components/Customers/CustomerFormModal.vue', formId: 'customer-form' },
    { file: 'Components/Users/UserFormModal.vue', formId: 'user-form' },
    { file: 'Components/Items/ItemFormModal.vue', formId: 'item-form' },
    { file: 'Components/Suppliers/SupplierFormModal.vue', formId: 'supplier-form' },
    { file: 'Components/Expenses/ExpenseFormModal.vue', formId: 'expense-form' },
    { file: 'Components/Stores/StoreFormModal.vue', formId: 'store-form' },
    { file: 'Components/Categories/CategoryFormModal.vue', formId: 'category-form' },
];

for (const { file, formId } of LONG_FORM_MODALS) {
    test(`${file} places Save/Cancel inside #footer and links submit to #${formId}`, () => {
        const src = read(file);
        const formMatch = src.match(/<form\b[^>]*>[\s\S]*?<\/form>/);
        const footerMatch = src.match(/<template #footer>[\s\S]*?<\/template>/);

        assert.ok(formMatch, `${file} contains <form>`);
        assert.ok(footerMatch, `${file} defines <template #footer>`);

        assert.match(formMatch[0], new RegExp(`id="${formId}"`));
        assert.doesNotMatch(
            formMatch[0],
            /type="submit"/,
            `${file} must not put main submit button inside the scrollable <form>`
        );

        assert.match(footerMatch[0], /type="submit"/);
        assert.match(footerMatch[0], new RegExp(`form="${formId}"`));
    });
}
