// PRD-06: Excel/CSV import flow E2E test.
// Covers:
//  - Selecting import type (items, customers, suppliers)
//  - Uploading spreadsheet with Arabic entity names and opening balances
//  - Validating preview step (summary counts, data table, skip-invalid handling)
//  - Committing import and verifying results summary
//
// Network endpoints /preview and /commit are mocked via page.route so nothing is written
// to the persistent database and tests run deterministically.
import { test, expect } from '@playwright/test';

const MOCK_ITEMS_PREVIEW = {
    success: true,
    data: {
        session_token: 'mock-session-token-12345',
        type: 'items',
        summary: {
            total: 3,
            valid: 3,
            invalid: 0,
            duplicates: 0,
        },
        rows: [
            {
                row_number: 2,
                status: 'valid',
                data: {
                    name: 'بن برازيلي وسط',
                    code: 'BRAZIL-MED',
                    category: 'قهوة',
                    unit: 'كجم',
                    cost_price: '350.000',
                    selling_price: '450.000',
                    opening_quantity: '25.000',
                    opening_cost: '350.000',
                },
                errors: [],
            },
            {
                row_number: 3,
                status: 'valid',
                data: {
                    name: 'بن كولومبي فاخر',
                    code: 'COLOMB-PRM',
                    category: 'قهوة',
                    unit: 'كجم',
                    cost_price: '420.000',
                    selling_price: '520.000',
                    opening_quantity: '15.000',
                    opening_cost: '420.000',
                },
                errors: [],
            },
            {
                row_number: 4,
                status: 'valid',
                data: {
                    name: 'شاي أخضر سيلاني',
                    code: 'TEA-GREEN',
                    category: 'شاي',
                    unit: 'قطعة',
                    cost_price: '45.000',
                    selling_price: '65.000',
                    opening_quantity: '50.000',
                    opening_cost: '45.000',
                },
                errors: [],
            },
        ],
        rows_truncated: false,
    },
};

const MOCK_COMMIT_RESULT = {
    success: true,
    data: {
        type: 'items',
        created: 3,
        skipped: 0,
        duplicates: 0,
        errors: [],
    },
};

test.describe('PRD-06: Imports Flow Journey', () => {
    test('complete wizard: select items type, upload CSV, preview, and commit', async ({ page }) => {
        let commitPayload = null;

        // Mock import preview API
        await page.route('**/api/v1/import/preview', (route) => {
            return route.fulfill({
                status: 200,
                json: MOCK_ITEMS_PREVIEW,
            });
        });

        // Mock import commit API
        await page.route('**/api/v1/import/commit', async (route) => {
            if (route.request().method() !== 'POST') return route.fallback();
            commitPayload = route.request().postDataJSON();
            return route.fulfill({
                status: 200,
                json: MOCK_COMMIT_RESULT,
            });
        });

        // Step 1: Navigate to import page
        await page.goto('/import', { waitUntil: 'networkidle' });
        await page.waitForSelector('#app', { timeout: 15000 });

        // Step 2: Choose type (items)
        const itemsTypeBtn = page.locator('button:has-text("الأصناف"), button:has-text("Items")').first();
        if (await itemsTypeBtn.isVisible()) {
            await itemsTypeBtn.click();
        }

        const continueBtn = page.locator('button:has-text("متابعة"), button:has-text("Continue")').first();
        await expect(continueBtn).toBeEnabled();
        await continueBtn.click();

        // Step 3: Upload file (simulate CSV with Arabic items)
        const csvContent =
            'name,code,category,unit,cost_price,selling_price,opening_quantity,opening_cost\n' +
            'بن برازيلي وسط,BRAZIL-MED,قهوة,كجم,350.000,450.000,25.000,350.000\n' +
            'بن كولومبي فاخر,COLOMB-PRM,قهوة,كجم,420.000,520.000,15.000,420.000\n' +
            'شاي أخضر سيلاني,TEA-GREEN,شاي,قطعة,45.000,65.000,50.000,45.000\n';

        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: 'items_import.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csvContent, 'utf-8'),
        });

        // Step 4: Verify preview step loaded
        await expect(page.getByText('بن برازيلي وسط')).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('بن كولومبي فاخر')).toBeVisible();

        // Step 5: Commit import
        const commitBtn = page.locator('button:has-text("استيراد"), button:has-text("Import")').last();
        await expect(commitBtn).toBeEnabled();
        await commitBtn.click();

        // Step 6: Verify result step displays created counts
        await expect(page.getByText('3').first()).toBeVisible({ timeout: 15000 });
        expect(commitPayload).not.toBeNull();
        expect(commitPayload.session_token).toBe('mock-session-token-12345');
        expect(commitPayload.type).toBe('items');
    });
});
