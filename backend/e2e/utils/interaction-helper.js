import { getScreenshotPath } from './screenshot-helper.js';

// Keywords that indicate destructive or critical actions that should NEVER be clicked during automated crawl
const DANGEROUS_KEYWORDS = [
    'حذف', 'مسح', 'delete', 'destroy', 'remove', 'force_delete', 'تفريغ',
    'إلغاء الفاتورة', 'إلغاء الحركة', 'تأكيد الحذف', 'نهائي', 'خروج', 'logout',
    'تصفير', 'إغلاق الوردية', 'تسليم الوردية', 'إعادة تعيين'
];

/**
 * Checks whether an element or its text represents a destructive action
 * @param {string} text 
 * @param {string} className 
 * @param {string} ariaLabel 
 */
export function isDangerousAction(text = '', className = '', ariaLabel = '') {
    const combined = `${text} ${className} ${ariaLabel}`.toLowerCase();
    return DANGEROUS_KEYWORDS.some(kw => combined.includes(kw.toLowerCase()));
}

/**
 * Safely inspects interactive elements, triggers modals/drawers, takes screenshots and closes them safely
 * @param {import('@playwright/test').Page} page 
 * @param {Object} pageInfo 
 * @param {string} viewportType - 'desktop' or 'mobile'
 * @param {Object} reportCollector 
 */
export async function explorePageInteractions(page, pageInfo, viewportType, reportCollector) {
    const interactiveElements = page.locator('button:visible, a[role="button"]:visible, [data-modal-target]:visible, button[aria-haspopup="dialog"]:visible');
    const count = await interactiveElements.count().catch(() => 0);

    let modalIndex = 1;

    for (let i = 0; i < Math.min(count, 15); i++) {
        try {
            const el = interactiveElements.nth(i);
            const isVisible = await el.isVisible().catch(() => false);
            if (!isVisible) continue;

            const text = (await el.innerText().catch(() => '')).trim();
            const ariaLabel = (await el.getAttribute('aria-label').catch(() => '')) || '';
            const className = (await el.getAttribute('class').catch(() => '')) || '';

            // 1. Skip dangerous / destructive actions
            if (isDangerousAction(text, className, ariaLabel)) {
                reportCollector.recordSkippedSensitiveButton({
                    page: pageInfo.route,
                    text: text || ariaLabel || 'Destructive Button',
                    reason: 'Critical or destructive action skipped for safety',
                });
                continue;
            }

            // 2. Check if button opens a modal or dropdown or tab
            const isModalOpener = text.includes('إضافة') || 
                                 text.includes('جديد') || 
                                 text.includes('إنشاء') || 
                                 text.includes('تعديل') || 
                                 text.includes('فلتر') || 
                                 text.includes('تصدير') || 
                                 text.includes('عرض') || 
                                 className.includes('modal') || 
                                 className.includes('drawer') ||
                                 ariaLabel.includes('modal');

            if (isModalOpener) {
                // Click to open modal/drawer
                await el.click({ timeout: 2000 }).catch(() => {});
                await page.waitForTimeout(400);

                // Check if dialog/modal or drawer appeared
                const modal = page.locator('[role="dialog"]:visible, .modal-open:visible, .fixed.inset-0:visible, .app-modal:visible');
                const isModalVisible = (await modal.count().catch(() => 0)) > 0;

                if (isModalVisible) {
                    const safeName = `${String(modalIndex).padStart(2, '0')}-${pageInfo.name}-modal-open-${modalIndex}`;
                    const modalScreenshotPath = getScreenshotPath(pageInfo.module, viewportType, `${safeName}.png`);
                    
                    await page.screenshot({ path: modalScreenshotPath, fullPage: false }).catch(() => {});
                    reportCollector.recordScreenshot(modalScreenshotPath);
                    modalIndex++;

                    // Try to safely close the modal via Escape, Cancel button or Close icon
                    const closeButton = modal.locator('button:has-text("إلغاء"), button:has-text("إغلاق"), button[aria-label="Close"], button[aria-label="إغلاق"], svg.lucide-x').first();
                    if ((await closeButton.count().catch(() => 0)) > 0) {
                        await closeButton.click({ timeout: 1500 }).catch(() => {});
                    } else {
                        await page.keyboard.press('Escape');
                    }
                    await page.waitForTimeout(300);
                }
            }
        } catch (_) {}
    }

    // 3. Discover unmapped navigation links on the page
    try {
        const links = page.locator('a[href]:visible');
        const linkCount = await links.count().catch(() => 0);
        for (let j = 0; j < linkCount; j++) {
            const href = await links.nth(j).getAttribute('href').catch(() => null);
            if (href && href.startsWith('/') && !href.startsWith('//') && !href.startsWith('/api') && !href.includes('#')) {
                reportCollector.recordDiscoveredRoute(href);
            }
        }
    } catch (_) {}
}
