import { test, expect } from '@playwright/test';

// Dummy fallback matches the dev seeder's default super admin (DatabaseSeeder::DEFAULT_SUPER_ADMIN_PHONE).
const E2E_USER_PHONE = process.env.E2E_USER_PHONE || '01000000001';

test.describe('1️⃣ موديول الدخول والجلسات (Authentication & Session E2E)', () => {
    test('TC-01: تسجيل دخول ناجح ببيانات صحيحة والانتقال للرئيسية', async ({ page }) => {
        await page.goto('/login');

        // Verify login page elements
        await expect(page.locator('text=تسجيل الدخول')).toBeVisible();

        // Fill credentials
        const phoneInput = page.locator('input[type="text"], input[type="tel"]').first();
        const passInput = page.locator('input[type="password"]').first();
        const submitBtn = page.locator('button[type="submit"]').first();

        await phoneInput.fill(E2E_USER_PHONE);
        await passInput.fill('password');
        await submitBtn.click();

        // Verify redirect to Dashboard
        await page.waitForURL('/', { timeout: 10000 });
        await expect(page.locator('text=مرحباً بك')).toBeVisible();
        await expect(page.locator('text=نبض أداء اليوم')).toBeVisible();
    });

    test('TC-02: منع الدخول عند كتابة كلمة مرور خاطئة', async ({ page }) => {
        await page.goto('/login');

        const phoneInput = page.locator('input[type="text"], input[type="tel"]').first();
        const passInput = page.locator('input[type="password"]').first();
        const submitBtn = page.locator('button[type="submit"]').first();

        await phoneInput.fill(E2E_USER_PHONE);
        await passInput.fill('wrong_password_123');
        await submitBtn.click();

        // Expect error message / stay on login
        await expect(page.locator('text=بيانات الدخول غير صحيحة, text=خطأ, text=Invalid').first())
            .toBeVisible({ timeout: 5000 })
            .catch(() => {
                // If error is a toast or text banner
                expect(page.url()).toContain('/login');
            });
        expect(page.url()).toContain('/login');
    });

    test('TC-03: بقاء الجلسة نشطة بعد إعادة تحميل الصفحة (Persistence)', async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[type="text"], input[type="tel"]').first().fill(E2E_USER_PHONE);
        await page.locator('input[type="password"]').first().fill('password');
        await page.locator('button[type="submit"]').first().click();
        await page.waitForURL('/');

        // Reload page
        await page.reload();
        await expect(page.locator('text=مرحباً بك')).toBeVisible();
        expect(page.url()).toBe('http://127.0.0.1:8080/');
    });

    test('TC-04: تسجيل الخروج وإبطال الجلسة والعودة للدخول', async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[type="text"], input[type="tel"]').first().fill(E2E_USER_PHONE);
        await page.locator('input[type="password"]').first().fill('password');
        await page.locator('button[type="submit"]').first().click();
        await page.waitForURL('/');

        // Open side menu drawer
        const menuBtn = page.locator('button:has-text("☰"), nav button:last-child').first();
        await menuBtn.click();

        // Click logout button
        const logoutBtn = page.locator('button:has-text("تسجيل الخروج"), button:has-text("خروج")').first();
        await expect(logoutBtn).toBeVisible();
        await logoutBtn.click();

        // Verify redirected back to login
        await page.waitForURL('/login', { timeout: 10000 });
        await expect(page.locator('text=تسجيل الدخول')).toBeVisible();
    });
});
