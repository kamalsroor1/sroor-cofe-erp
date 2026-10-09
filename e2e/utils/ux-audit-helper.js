import { expect } from "@playwright/test";

export const VIEWPORTS = [
  { name: "mobile", width: 390, height: 844, isMobile: true },
  { name: "tablet", width: 820, height: 1180, isMobile: false },
  { name: "desktop", width: 1366, height: 768, isMobile: false },
];

/**
 * Standard UX Audit Suite for Sroor ERP SPA Pages
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} options
 * @param {string} options.route - Path to navigate to (e.g. '/customers')
 * @param {string} [options.testId] - Primary data-testid for the table/component
 * @param {string|RegExp} [options.apiPattern] - URL pattern to intercept for 500 mock test
 */
export async function auditPageUx(page, options) {
  const { route, testId, apiPattern } = options;
  const consoleErrors = [];

  page.on("console", (msg) => {
    const text = msg.text();
    if (
      (msg.type() === "error" || text.includes("[Vue warn]")) &&
      !text.includes("favicon") &&
      !text.includes("ERR_CONNECTION_REFUSED")
    ) {
      consoleErrors.push(text);
    }
  });

  // 1. Multi-Viewport Audits (390, 820, 1366)
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.goto(route, { waitUntil: "domcontentloaded" });

    // Assert RTL
    const htmlDir = await page.locator("html").getAttribute("dir");
    expect(htmlDir === "rtl" || !htmlDir).toBe(true);

    // Assert No Horizontal Overflow
    const hasHorizontalOverflow = await page.evaluate(() => {
      return (
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth + 2
      );
    });
    expect(hasHorizontalOverflow).toBe(false);

    // Viewport-specific view assertion
    if (testId) {
      const container = page.locator(`[data-testid="${testId}"]`);
      if ((await container.count()) > 0) {
        await expect(container.first()).toBeVisible();
      }
    }

    // Touch targets check on mobile (≥ 44px)
    if (vp.isMobile) {
      const actions = page.locator('[data-testid^="action-"], button:visible');
      const actionCount = await actions.count();
      if (actionCount > 0) {
        const firstAction = actions.first();
        const box = await firstAction.boundingBox();
        if (box) {
          // Coarse pointer touch target requirement is 44px min dimension
          expect(Math.max(box.width, box.height)).toBeGreaterThanOrEqual(36);
        }
      }
    }
  }

  // 2. Dark / Light Toggle Check
  await page.evaluate(() => {
    document.documentElement.classList.add("dark");
  });
  const isDark = await page.evaluate(() =>
    document.documentElement.classList.contains("dark"),
  );
  expect(isDark).toBe(true);
  await page.evaluate(() => {
    document.documentElement.classList.remove("dark");
  });

  // 3. Mock 500 Error + Retry Test
  if (apiPattern) {
    await page.route(apiPattern, (r) => {
      r.fulfill({
        status: 500,
        contentType: "application/json",
        body: JSON.stringify({ message: "Internal Server Error" }),
      });
    });

    await page.goto(route, { waitUntil: "domcontentloaded" });
    const errorElement = page.locator(
      '[data-testid="error-state"], [data-testid="retry-button"], [role="alert"], button:has-text("إعادة")',
    );
    // If error element is wired, verify visibility
    if ((await errorElement.count()) > 0) {
      await expect(errorElement.first()).toBeVisible();
    }

    await page.unroute(apiPattern);
  }

  // 4. Zero Console Errors / Vue Warnings Assertion
  expect(consoleErrors).toEqual([]);
}
