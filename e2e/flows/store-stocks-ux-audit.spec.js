import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Store Stocks UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies store stocks page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/store-stocks',
            testId: 'store-stocks-table',
            apiPattern: '**/api/v1/stores/stocks*',
        });
    });
});
