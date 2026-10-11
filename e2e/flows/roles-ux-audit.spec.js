import { test } from '@playwright/test';
import { auditPageUx } from '../utils/ux-audit-helper.js';

test.describe('Roles UX Audit', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    test('verifies roles page responsive layout, RTL, dark/light, and error states', async ({ page }) => {
        await auditPageUx(page, {
            route: '/roles',
            apiPattern: '**/api/v1/roles*',
        });
    });
});
