import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Suppliers UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies suppliers page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/suppliers',
            testId: 'suppliers-table',
            apiPattern: '**/api/v1/suppliers*',
        });
    });
});
