import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Customers UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies customers page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/customers',
            testId: 'customers-table',
            apiPattern: '**/api/v1/customers*',
        });
    });
});
