import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Categories UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies categories page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/categories',
            apiPattern: '**/api/v1/categories*',
        });
    });
});
