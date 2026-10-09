import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Users UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies users page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/users',
            testId: 'users-table',
            apiPattern: '**/api/v1/users*',
        });
    });
});
