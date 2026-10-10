import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Purchases UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies purchases page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/purchases',
            testId: 'purchases-table',
            apiPattern: '**/api/v1/purchases*',
        });
    });
});
