import { test } from '@playwright/test';
import { pagesConfig } from '../pages.config.js';
import { getScreenshotPath } from '../utils/screenshot-helper.js';
import { explorePageInteractions } from '../utils/interaction-helper.js';
import { globalReportCollector } from '../utils/reporter-helper.js';

test.describe('E2E Visual Crawler & Interaction Engine', () => {
    test.afterAll(async () => {
        const registeredRoutes = pagesConfig.map(p => p.route);
        globalReportCollector.saveReports(registeredRoutes);
    });

    pagesConfig.forEach((pageInfo, index) => {
        const formattedIndex = String(index + 1).padStart(2, '0');
        const testTitle = `[${pageInfo.module}] ${pageInfo.title} (${pageInfo.route})`;

        test(`${testTitle} @${pageInfo.module}`, async ({ page }, testInfo) => {
            const viewport = page.viewportSize();
            const isMobile = viewport && viewport.width < 768;
            const viewportType = isMobile ? 'mobile' : 'desktop';

            // 1. Listen for runtime console errors
            page.on('console', msg => {
                if (msg.type() === 'error') {
                    globalReportCollector.recordConsoleError(pageInfo.route, msg.text());
                }
            });

            page.on('pageerror', err => {
                globalReportCollector.recordConsoleError(pageInfo.route, err.message);
            });

            try {
                // 2. Navigate to route with network idle wait
                await page.goto(pageInfo.route, {
                    waitUntil: 'networkidle',
                    timeout: 25000,
                });

                // 3. Wait for Vue SPA component to fully mount its template inside #app
                await page.waitForSelector('#app > *', { timeout: 15000 }).catch(() => {});
                await page.waitForTimeout(600); // Allow icons, animations and charts to render

                // 4. Capture primary full-page screenshot
                const baseFileName = `${formattedIndex}-${pageInfo.name}.png`;
                const mainScreenshotPath = getScreenshotPath(pageInfo.module, viewportType, baseFileName);

                await page.screenshot({
                    path: mainScreenshotPath,
                    fullPage: true,
                    animations: 'disabled',
                });

                globalReportCollector.recordSuccess(pageInfo, viewportType, mainScreenshotPath);

                // 5. Explore interactive modals/drawers safely
                await explorePageInteractions(page, pageInfo, viewportType, globalReportCollector);

            } catch (error) {
                console.error(`❌ Crawler error on page [${pageInfo.route}]:`, error.message);
                globalReportCollector.recordFailure(pageInfo, viewportType, error);
            }
        });
    });
});
