import fs from 'fs';
import path from 'path';
import { getScreenshotRunDir, getRunTimestamp } from './screenshot-helper.js';

class ReportCollector {
    constructor() {
        this.successfulPages = [];
        this.failedPages = [];
        this.consoleErrors = [];
        this.discoveredRoutes = new Set();
        this.skippedSensitiveButtons = [];
        this.screenshots = [];
        this.startTime = Date.now();
    }

    recordSuccess(pageInfo, viewportType, screenshotPath) {
        this.successfulPages.push({
            route: pageInfo.route,
            name: pageInfo.name,
            module: pageInfo.module,
            viewport: viewportType,
            screenshot: screenshotPath,
            timestamp: new Date().toISOString(),
        });
        this.screenshots.push(screenshotPath);
    }

    recordFailure(pageInfo, viewportType, error) {
        this.failedPages.push({
            route: pageInfo.route,
            name: pageInfo.name,
            module: pageInfo.module,
            viewport: viewportType,
            error: error?.message || String(error),
            timestamp: new Date().toISOString(),
        });
    }

    recordConsoleError(pageRoute, message) {
        this.consoleErrors.push({
            page: pageRoute,
            message: String(message),
            timestamp: new Date().toISOString(),
        });
    }

    recordDiscoveredRoute(route) {
        this.discoveredRoutes.add(route);
    }

    recordSkippedSensitiveButton(buttonInfo) {
        this.skippedSensitiveButtons.push(buttonInfo);
    }

    recordScreenshot(screenshotPath) {
        this.screenshots.push(screenshotPath);
    }

    saveReports(registeredRoutes = []) {
        const runDir = getScreenshotRunDir();
        const durationSeconds = ((Date.now() - this.startTime) / 1000).toFixed(1);
        const { dateStr, runId } = getRunTimestamp();

        // Filter truly new discovered routes not in pages.config.js
        const registeredSet = new Set(registeredRoutes.map(r => r.replace(/\/1\//g, '/:id/')));
        const newDiscovered = Array.from(this.discoveredRoutes).filter(r => {
            const normalized = r.replace(/\/\d+\//g, '/:id/');
            return !registeredSet.has(normalized) && !registeredSet.has(r);
        });

        const reportData = {
            summary: {
                totalSuccess: this.successfulPages.length,
                totalFailed: this.failedPages.length,
                totalScreenshots: this.screenshots.length,
                totalDiscovered: newDiscovered.length,
                totalConsoleErrors: this.consoleErrors.length,
                totalSkippedSensitiveButtons: this.skippedSensitiveButtons.length,
                durationSeconds: Number(durationSeconds),
                runDate: dateStr,
                runId: runId,
                runDirectory: runDir,
            },
            successfulPages: this.successfulPages,
            failedPages: this.failedPages,
            discoveredNewRoutes: newDiscovered,
            consoleErrors: this.consoleErrors,
            skippedSensitiveButtons: this.skippedSensitiveButtons,
        };

        // 1. Save _report.json
        const jsonPath = path.join(runDir, '_report.json');
        fs.writeFileSync(jsonPath, JSON.stringify(reportData, null, 2), 'utf8');

        // 2. Save _report.md
        const mdContent = `# 📸 تقرير فحص الشاشات والـ E2E Visual Crawling
* **تاريخ التشغيل:** ${dateStr} (${runId})
* **مدة الفحص:** ${durationSeconds} ثانية
* **مسار حفظ الصور:** \`${runDir}\`

---

## 📊 ملخص النتائج (Execution Summary)
* ✅ **الصفحات الملتقطة بنجاح:** ${this.successfulPages.length} صفحة
* ⚠️ **الصفحات التي فشلت:** ${this.failedPages.length} صفحة
* 📸 **إجمالي الـ Screenshots الملتقطة:** ${this.screenshots.length} لقطة
* 🔍 **صفحات وروابط جديدة مكتشفة:** ${newDiscovered.length} رابط
* 🚫 **أزرار حساسة تم تخطيها بأمان:** ${this.skippedSensitiveButtons.length} زرار
* 🐞 **أخطاء Console المسجلة:** ${this.consoleErrors.length} خطأ

---

## ❌ تفاصيل الصفحات الفاشلة (إن وجدت):
${this.failedPages.length === 0 ? '_لا توجد صفحات فاشلة، كافة الشاشات التقطت بنجاح 100%._' : this.failedPages.map(f => `* **[${f.module}] ${f.route} (${f.viewport}):** \`${f.error}\``).join('\n')}

---

## 🔍 الروابط والصفحات الجديدة المكتشفة:
${newDiscovered.length === 0 ? '_لا توجد صفحات غير مسجلة._' : newDiscovered.map(r => `* \`${r}\``).join('\n')}

---

## 🚫 الأزرار الحساسة المستبعدة للأمان:
${this.skippedSensitiveButtons.slice(0, 20).map(b => `* \`${b.page}\`: ${b.text}`).join('\n')}
`;

        const mdPath = path.join(runDir, '_report.md');
        fs.writeFileSync(mdPath, mdContent, 'utf8');

        // 3. Print Terminal Summary
        console.log('\n======================================================');
        console.log(`✅ تم تصوير ${this.successfulPages.length} صفحة بنجاح`);
        if (this.failedPages.length > 0) {
            console.log(`⚠️ ${this.failedPages.length} صفحات فشلت (تفاصيلها في _report.json)`);
        }
        if (newDiscovered.length > 0) {
            console.log(`🔍 ${newDiscovered.length} صفحات جديدة مكتشفة مش مسجلة في pages.config.js`);
        }
        console.log(`📁 الصور محفوظة في: ${runDir}`);
        console.log('======================================================\n');
    }
}

export const globalReportCollector = new ReportCollector();
export default globalReportCollector;
