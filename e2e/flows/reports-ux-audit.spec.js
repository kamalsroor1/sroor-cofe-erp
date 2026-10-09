import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Reports UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies reports page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/reports',
            apiPattern: '**/api/v1/reports*',
        });
    });
});
