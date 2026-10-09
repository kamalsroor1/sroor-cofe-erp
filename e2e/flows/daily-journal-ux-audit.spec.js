import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Daily Journal UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies daily journal page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/daily-journal',
            apiPattern: '**/api/v1/shifts*',
        });
    });
});
