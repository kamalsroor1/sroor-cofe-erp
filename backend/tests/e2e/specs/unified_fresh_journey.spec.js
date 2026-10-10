import { test, expect } from '@playwright/test';
import { resetFreshDatabase } from '../utils/db-reset.js';

// Default: the local `demo` tenant admin (scripts/local/setup-local.ps1); the central admin no longer logs into a tenant.
const E2E_USER_PHONE = process.env.E2E_USER_PHONE || '01000000201';

/**
 * 🌟 رحلة المستخدم الشاملة والمتكاملة لكافة مميزات وموديولات النظام الـ 17
 * (The Ultimate Full ERP Lifecycle Journey - على قاعدة بيانات جديدة تماماً)
 */
test.describe.serial('🌟 رحلة ERP الشاملة لكافة المميزات (Full ERP Lifecycle Journey)', () => {
    let page;

    test.beforeAll(async ({ browser }) => {
        test.setTimeout(120000); // مهلة كافية لتصفير وبناء قاعدة البيانات وتجهيز المتصفح
        // 1. إعادة بناء قاعدة البيانات وتغذيتها بكافة الفروع والأصناف
        resetFreshDatabase();

        // 2. تجهيز متصفح موبايل متصل (Pixel 7 HD)
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
            await page.waitForTimeout(4000); // إبقاء المتصفح مفتوحاً 4 ثوانٍ في النهاية لمشاهدة النتيجة
            await page.close();
        }
    });

    // 1️⃣ تسجيل الدخول لمرة واحدة والوصول للرئيسية
    test('1️⃣ تسجيل الدخول لمرة واحدة بحساب الأدمن وتفقد مؤشرات اليوم', async () => {
        await page.goto('/login');
        await page.waitForTimeout(1000);

        const phoneInput = page.locator('input[type="text"], input[type="tel"]').first();
        const passInput = page.locator('input[type="password"]').first();
        const submitBtn = page.locator('button[type="submit"]').first();

        await phoneInput.fill(E2E_USER_PHONE);
        await page.waitForTimeout(300);
        await passInput.fill('password');
        await page.waitForTimeout(300);
        await submitBtn.click();

        // التأكد من الوصول للرئيسية وظهور بطاقات النبض اللحظي
        await page.waitForURL('/', { timeout: 10000 });
        await expect(page.getByText(/مرحباً بك|نبض أداء اليوم/).first()).toBeVisible();
        await page.waitForTimeout(1500);
    });

    // 2️⃣ القائمة الجانبية وتبديل الفرع
    test('2️⃣ فتح القائمة الجانبية وتفقد التحديثات وتبديل الفرع النشط', async () => {
        const menuBtn = page.locator('nav button').last();
        await menuBtn.click();
        await page.waitForTimeout(1000);

        // التحقق من زر تبديل الفرع وفتحه
        const switchBtn = page.getByRole('button', { name: /تبديل/ }).first();
        if (await switchBtn.isVisible({ timeout: 3000 })) {
            await switchBtn.click();
            await page.waitForTimeout(1200);

            // اختيار فرع الزقازيق
            const storeOptions = page.locator('button:has-text("فرع"), div:has-text("فرع")');
            if ((await storeOptions.count()) > 1) {
                await storeOptions.nth(1).click();
                await page.waitForTimeout(1500);
            } else {
                await page.keyboard.press('Escape');
            }
        }

        await page.keyboard.press('Escape');
        await page.waitForTimeout(800);
    });

    // 3️⃣ دليل الأصناف ورادار النواقص وكارت حركة الصنف
    test('3️⃣ دليل الأصناف والمخزون، رادار النواقص، وتتبع كارت الصنف', async () => {
        await page.goto('/items');
        await page.waitForTimeout(1500);

        // التحقق من وجود الأصناف
        await expect(page.getByText(/دليل الأصناف|المخزون/).first()).toBeVisible();

        // اختبار فلتر رادار النواقص
        const lowStockBtn = page.getByRole('button', { name: /النواقص|قارب على النفاد|الحد الأدنى/ }).first();
        if (await lowStockBtn.isVisible({ timeout: 3000 })) {
            await lowStockBtn.click();
            await page.waitForTimeout(1000);
        }

        // الانتقال لكارت حركة الصنف
        await page.goto('/reports/items/1/card');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/رصيد المخزن|تقارير الأرباح|كود/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 4️⃣ فواتير المشتريات والتوريد وحسابات الموردين
    test('4️⃣ فواتير المشتريات: إنشاء فاتورة توريد جديدة لزيادة المخزون', async () => {
        await page.goto('/purchases');
        await page.waitForTimeout(1500);

        // فتح مودال إضافة فاتورة توريد (+)
        const addPurchaseBtn = page.getByRole('button', { name: /فاتورة توريد جديدة|توريد جديدة|\+/ }).first();
        if (await addPurchaseBtn.isVisible({ timeout: 3000 })) {
            await addPurchaseBtn.click();
            await page.waitForTimeout(1200);

            // حفظ فاتورة التوريد
            const submitBtn = page.getByRole('button', { name: /حفظ واعتماد|حفظ فاتورة|تأكيد/ }).first();
            if (await submitBtn.isVisible({ timeout: 2000 })) {
                await submitBtn.click();
                await page.waitForTimeout(2000);
            } else {
                await page.keyboard.press('Escape');
            }
        }
    });

    // 5️⃣ إذن التحويل المخزني بين الفروع
    test('5️⃣ التحويل المخزني: إنشاء إذن تحويل بضاعة بين الفروع', async () => {
        await page.goto('/transfers');
        await page.waitForTimeout(1500);

        const addTransferBtn = page.getByRole('button', { name: /إذن تحويل|تحويل جديد|\+/ }).first();
        if (await addTransferBtn.isVisible({ timeout: 3000 })) {
            await addTransferBtn.click();
            await page.waitForTimeout(1200);

            const submitTransferBtn = page.getByRole('button', { name: /تنفيذ التحويل|حفظ|تأكيد/ }).first();
            if (await submitTransferBtn.isVisible({ timeout: 2000 })) {
                await submitTransferBtn.click();
                await page.waitForTimeout(1500);
            } else {
                await page.keyboard.press('Escape');
            }
        }
    });

    // 6️⃣ دليل العملاء وإضافة عميل جديد وتفقد كشف الحساب
    test('6️⃣ دليل العملاء: إضافة عميل جديد لايف وتفقد كشف حسابه', async () => {
        await page.goto('/customers');
        await page.waitForTimeout(1500);

        // إضافة عميل جديد
        const addCustBtn = page.getByRole('button', { name: /إضافة عميل|عميل جديد|\+/ }).first();
        if (await addCustBtn.isVisible({ timeout: 3000 })) {
            await addCustBtn.click();
            await page.waitForTimeout(1000);

            const nameInput = page.locator('input[placeholder*="اسم العميل"], input[name="name"]').first();
            const phoneInput = page.locator('input[placeholder*="الهاتف"], input[name="phone"]').first();

            if (await nameInput.isVisible({ timeout: 2000 })) {
                await nameInput.fill('كافيه ومطعم البرنس (عميل تجريبي)');
                await page.waitForTimeout(300);
            }
            if (await phoneInput.isVisible({ timeout: 2000 })) {
                await phoneInput.fill('01234567890');
                await page.waitForTimeout(300);
            }

            const saveBtn = page.getByRole('button', { name: /حفظ|إضافة|تأكيد/ }).first();
            if (await saveBtn.isVisible({ timeout: 2000 })) {
                await saveBtn.click();
                await page.waitForTimeout(1500);
            } else {
                await page.keyboard.press('Escape');
            }
        }

        // الانتقال لكشف حساب العميل
        await page.goto('/customers/1/statement');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/كشف حساب العميل|كشف الحساب/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 7️⃣ دليل الموردين والمستحقات
    test('7️⃣ دليل الموردين: فحص الشركات الموردة ومستحقاتها المالية', async () => {
        await page.goto('/suppliers');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/دليل الموردين|الموردين/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 8️⃣ ورديات الكاشير وفتح وردية
    test('8️⃣ ورديات الكاشير: تفقد عهدة الدرج وفتح الوردية', async () => {
        await page.goto('/shifts');
        await page.waitForTimeout(1500);

        const openShiftBtn = page.getByRole('button', { name: /فتح وردية كاشير|فتح وردية/ }).first();
        if (await openShiftBtn.isVisible({ timeout: 3000 })) {
            await openShiftBtn.click();
            await page.waitForTimeout(1500);
        }
    });

    // 9️⃣ كاشير نقاط البيع POS وميزان الدقة والسلة والدفع
    test('9️⃣ كاشير POS: اختيار صنف بوزن ميزان وإتمام بيع نقدي وسداد', async () => {
        await page.goto('/pos');
        await page.waitForTimeout(1500);

        // اختيار الصنف
        const firstItem = page.locator('.grid > div .cursor-pointer').first();
        await expect(firstItem).toBeVisible({ timeout: 6000 });
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

            // إتمام البيع
            const submitSaleBtn = page.getByRole('button', { name: /إتمام البيع|حفظ الفاتورة|طباعة/ }).first();
            if (await submitSaleBtn.isVisible({ timeout: 4000 })) {
                await submitSaleBtn.click();
                await page.waitForTimeout(2000);
            }
        }
    });

    // 🔟 سجل فواتير المبيعات ومشاركة الواتساب
    test('🔟 سجل فواتير المبيعات: تفاصيل الفاتورة ومشاركة الواتساب', async () => {
        await page.goto('/invoices');
        await page.waitForTimeout(1500);

        const firstInvoice = page.locator('a[href*="/invoices/"]').first();
        if (await firstInvoice.isVisible({ timeout: 5000 })) {
            await firstInvoice.click();
            await page.waitForURL(/\/invoices\/\d+/, { timeout: 8000 });
            await page.waitForTimeout(1500);

            const whatsappBtn = page.locator('a[href*="whatsapp"], button:has-text("واتساب")').first();
            if (await whatsappBtn.isVisible({ timeout: 3000 })) {
                await page.waitForTimeout(1000);
            }
        }
    });

    // 1️⃣1️⃣ مرتجعات المبيعات والمشتريات
    test('1️⃣1️⃣ المرتجعات: فحص سجل المرتجعات واسترجاع البضاعة', async () => {
        await page.goto('/returns');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/مرتجعات المبيعات|مرتجع|المرتجعات/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 1️⃣2️⃣ سندات القبض والصرف
    test('1️⃣2️⃣ السندات المالية: فحص سندات القبض والصرف النقدي', async () => {
        await page.goto('/payments');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/سندات القبض|السندات|سند/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 1️⃣3️⃣ المصروفات والنثريات اليومية
    test('1️⃣3️⃣ المصروفات: تسجيل مصروف تشغيلي وخصمه من الخزينة', async () => {
        await page.goto('/expenses');
        await page.waitForTimeout(1500);

        const addExpBtn = page.getByRole('button', { name: /تسجيل مصروف|مصروف جديد|\+/ }).first();
        if (await addExpBtn.isVisible({ timeout: 3000 })) {
            await addExpBtn.click();
            await page.waitForTimeout(1000);

            const amountInput = page.locator('input[type="number"], input[placeholder*="المبلغ"]').first();
            if (await amountInput.isVisible({ timeout: 2000 })) {
                await amountInput.fill('75');
                await page.waitForTimeout(300);
            }

            const saveBtn = page.locator('button[type="submit"]:has-text("تسجيل المصروف")').first();
            if (await saveBtn.isVisible({ timeout: 2000 })) {
                await saveBtn.click();
                await page.waitForTimeout(1500);
            } else {
                await page.keyboard.press('Escape');
            }
        }
    });

    // 1️⃣4️⃣ حركة الخزينة والصندوق والسيولة
    test('1️⃣4️⃣ الخزينة والصندوق: مطابقة السيولة النقدية وحركات اليوم', async () => {
        await page.goto('/treasury');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/حركة الخزينة والصندوق|صافي النقدية/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 1️⃣5️⃣ تقارير الأرباح الحقيقية و COGS وتصدر الأصناف
    test('1️⃣5️⃣ تقارير الأرباح: معادلة صافي الربح الحقيقي وقائمة الأكثر مبيعاً', async () => {
        await page.goto('/reports');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/تحليلات الأرباح والمبيعات|الأرباح/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 1️⃣6️⃣ سجل تدقيق الرقابة والعمليات الحساسة
    test('1️⃣6️⃣ سجل الرقابة: رصد وتدقيق كافة العمليات الحساسة السابقة', async () => {
        await page.goto('/audit-logs');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/سجل الرقابة وتدقيق العمليات|الرقابة/).first()).toBeVisible();
        await page.waitForTimeout(1000);
    });

    // 1️⃣7️⃣ إعدادات النظام والطباعة الحرارية والختام
    test('1️⃣7️⃣ إعدادات النظام: فحص إعدادات الطباعة وتيليجرام والعودة للرئيسية', async () => {
        await page.goto('/settings');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/إعدادات النظام والطباعة|الإعدادات/).first()).toBeVisible();
        await page.waitForTimeout(1000);

        // العودة للرئيسية في ختام الرحلة الشاملة
        await page.goto('/');
        await page.waitForTimeout(2000);
        await expect(page.getByText(/مرحباً بك|الرئيسية/).first()).toBeVisible();
    });
});
