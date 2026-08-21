import { test, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { getScreenshotRunDir } from '../utils/screenshot-helper.js';

test.describe('Flow: In-App APK Auto-Updater & Releases Suite', () => {
    test('Verify In-App Update Modal, Progress Bar & Super Admin Management', async ({ page }) => {
        const runDir = getScreenshotRunDir();
        const flowDir = path.join(runDir, 'flows', 'app-update-flow');
        if (!fs.existsSync(flowDir)) {
            fs.mkdirSync(flowDir, { recursive: true });
        }

        // Step 1: Open Dashboard (App auto checks for updates and displays modal)
        await page.goto('/', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        // Capture screenshot of In-App Update Modal
        await page.screenshot({ path: path.join(flowDir, '01-in-app-update-modal-visible.png'), fullPage: true });

        // Step 2: Click "تحديث وتثبيت الآن ⚡" and capture progress state
        const updateButton = page.locator('button:has-text("تحديث وتثبيت")').first();
        if (await updateButton.isVisible()) {
            await updateButton.click();
            await page.waitForTimeout(300);
            await page.screenshot({ path: path.join(flowDir, '02-download-progress-active.png'), fullPage: true });
            await page.waitForTimeout(1200);
        }

        // Close SweetAlert if open
        const swalConfirm = page.locator('.swal2-confirm');
        if (await swalConfirm.isVisible()) {
            await swalConfirm.click();
            await page.waitForTimeout(300);
        }

        // Step 3: Navigate to Settings -> System section & Check Updates Button
        await page.goto('/settings', { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);

        // Click on System Information section
        const systemSectionButton = page.locator('button:has-text("معلومات النظام"), div:has-text("معلومات النظام")').first();
        if (await systemSectionButton.isVisible()) {
            await systemSectionButton.click();
            await page.waitForTimeout(500);
        }
        await page.screenshot({ path: path.join(flowDir, '03-settings-system-updates-card.png'), fullPage: true });

        // Step 4: Navigate to Super Admin App Versions Management
        await page.goto('/super-admin/app-versions', { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);
        await page.screenshot({ path: path.join(flowDir, '04-super-admin-releases-management.png'), fullPage: true });

        // Open Upload Modal
        const publishButton = page.locator('button:has-text("نشر إصدار APK جديد")').first();
        if (await publishButton.isVisible()) {
            await publishButton.click();
            await page.waitForTimeout(500);
            await page.screenshot({ path: path.join(flowDir, '05-super-admin-upload-apk-modal.png'), fullPage: true });
        }
    });
});
