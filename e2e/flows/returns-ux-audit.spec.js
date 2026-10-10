import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Returns UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies returns page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/returns',
            testId: 'returns-table',
            apiPattern: '**/api/v1/returns*',
        });
    });
});
