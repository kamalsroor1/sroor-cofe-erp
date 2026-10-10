import { test, expect } from '@playwright/test';

// Default: the local `demo` tenant admin (scripts/local/setup-local.ps1); the central admin no longer logs into a tenant.
const E2E_USER_PHONE = process.env.E2E_USER_PHONE || '01000000201';

/**
 * 🌟 سيناريو رحلة المستخدم المتصلة والمتسلسلة من البداية للنهاية
 * (Single Continuous Live Journey - جلسة واحدة متصلة بدون قفل المتصفح أو إعادة الدخول)
 */
test.describe.serial('🌟 رحلة المستخدم الشاملة والمتصلة (Continuous ERP Journey)', () => {
    let page;

    test.beforeAll(async ({ browser }) => {
        // إنشاء صفحة واحدة ومحاكاة موبايل Pixel 7
        const context = await browser.newContext({
            viewport: { width: 412, height: 915 },
            isMobile: true,
            hasTouch: true,
            locale: 'ar-EG',
            timezoneId: 'Africa/Cairo',
        });
        page = await context.newPage();
    });

    test.afterAll(async () => {
        if (page) {
            await page.waitForTimeout(3000); // إبقاء المتصفح مفتوحاً 3 ثوانٍ في النهاية لمشاهدة النتيجة
            await page.close();
        }
    });

    // 1️⃣ الخطوة الأولى: تسجيل الدخول لمرة واحدة فقط
    test('1️⃣ تسجيل الدخول لمرة واحدة والوصول للرئيسية', async () => {
        await page.goto('/login');
        await page.waitForTimeout(1000);

        const phoneInput = page.locator('input[type="text"], input[type="tel"]').first();
        const passInput = page.locator('input[type="password"]').first();
        const submitBtn = page.locator('button[type="submit"]').first();

        await phoneInput.fill(E2E_USER_PHONE);
        await page.waitForTimeout(400);
        await passInput.fill('password');
        await page.waitForTimeout(400);
        await submitBtn.click();

        // التحقق من الوصول للشاشة الرئيسية
        await page.waitForURL('/', { timeout: 10000 });
        await expect(page.getByText(/مرحباً بك|نبض اليوم/).first()).toBeVisible();
        await page.waitForTimeout(1500);
    });

    // 2️⃣ الخطوة الثانية: فتح القائمة الجانبية وتبديل الفرع
    test('2️⃣ فتح القائمة الجانبية وتفقد الفرع', async () => {
        // فتح القائمة الجانبية
        const menuBtn = page.locator('nav button').last();
        await menuBtn.click();
        await page.waitForTimeout(1000);

        // فتح نافذة تبديل الفرع
        const switchBtn = page.getByRole('button', { name: /تبديل/ }).first();
        if (await switchBtn.isVisible({ timeout: 3000 })) {
            await switchBtn.click();
            await page.waitForTimeout(1200);

            // إغلاق نافذة التبديل والعودة
            await page.keyboard.press('Escape');
            await page.waitForTimeout(800);
        }

        // إغلاق القائمة الجانبية
        await page.keyboard.press('Escape');
        await page.waitForTimeout(800);
    });

    // 3️⃣ الخطوة الثالثة: الانتقال لشاشة الورديات وفحص الخزينة
    test('3️⃣ الانتقال للورديات وتفقد حالة الدرج والـ Z-Report', async () => {
        await page.goto('/shifts');
        await page.waitForTimeout(1500);

        // التحقق من عنوان الورديات
        await expect(page.getByText(/إدارة ورديات الكاشير|الورديات/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 4️⃣ الخطوة الرابعة: الذهاب للكاشير، اختيار صنف بالوزن، إتمام سداد كاش
    test('4️⃣ الانتقال لكاشير POS، إضافة بضاعة بالوزن، وإتمام بيع سريع', async () => {
        await page.goto('/pos');
        await page.waitForTimeout(1500);

        // التحقق من شبكة الأصناف
        const firstItem = page.locator('.grid > div .cursor-pointer').first();
        await expect(firstItem).toBeVisible();
        await firstItem.click();
        await page.waitForTimeout(1000);

        // تأكيد إضافة الوزن في المودال
        const confirmBtn = page.getByRole('button', { name: /إضافة للفاتورة|تأكيد|إضافة/ }).first();
        if (await confirmBtn.isVisible({ timeout: 4000 })) {
            await confirmBtn.click();
            await page.waitForTimeout(1000);
        }

        // فتح سلة الدفع
        const cartBtn = page.locator('button:has-text("السلة")').first();
        if (await cartBtn.isVisible({ timeout: 4000 })) {
            await cartBtn.click();
            await page.waitForTimeout(1200);

            // الضغط على إتمام البيع وحفظ الفاتورة
            const submitSaleBtn = page.getByRole('button', { name: /إتمام البيع|حفظ الفاتورة|طباعة/ }).first();
            if (await submitSaleBtn.isVisible({ timeout: 4000 })) {
                await submitSaleBtn.click();
                await page.waitForTimeout(2000);
            }
        }
    });

    // 5️⃣ الخطوة الخامسة: الانتقال لسجل الفواتير وتفقد الفاتورة والواتساب
    test('5️⃣ الانتقال لسجل الفواتير وتفقد تفاصيل الفاتورة ومشاركة الواتساب', async () => {
        await page.goto('/invoices');
        await page.waitForTimeout(1500);

        // فتح تفاصيل أول فاتورة
        const firstInvoice = page.locator('a[href*="/invoices/"]').first();
        if (await firstInvoice.isVisible({ timeout: 5000 })) {
            await firstInvoice.click();
            await page.waitForURL(/\/invoices\/\d+/, { timeout: 8000 });
            await page.waitForTimeout(1500);

            // تفقد زر الواتساب والطباعة
            const whatsappBtn = page.locator('a[href*="whatsapp"], button:has-text("واتساب")').first();
            if (await whatsappBtn.isVisible({ timeout: 3000 })) {
                await page.waitForTimeout(1000);
            }
        }

        // العودة للرئيسية في ختام الرحلة
        await page.goto('/');
        await page.waitForTimeout(2000);
        await expect(page.getByText(/مرحباً بك|الرئيسية/).first()).toBeVisible();
    });
});
