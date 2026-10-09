import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Dashboard UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies dashboard responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/',
            testId: 'dashboard-recent-invoices-table',
            apiPattern: '**/api/v1/dashboard*',
        });
    });
});
