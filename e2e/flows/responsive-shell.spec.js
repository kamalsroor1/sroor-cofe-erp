import { test, expect } from "@playwright/test";

// RESP-01: app-shell breakpoints (helpers/breakpoints.js): < 1024 drawer + bottom nav,
// 1024-1279 sidebar rail with overlay expand, >= 1280 open sidebar remembered per user.
test.beforeEach(({ browserName }, testInfo) => {
  test.skip(
    testInfo.project.name !== "desktop",
    "Sets its own viewports; runs once in the desktop project.",
  );
});

const ROUTES = ["/", "/invoices", "/items", "/returns/create", "/reports"];
const SIZES = [
  { width: 768, height: 1024, minMainRatio: 0.9 },
  { width: 1024, height: 768, minMainPx: 920 },
  { width: 1280, height: 720, minMainPx: 1000 },
];

async function openRoute(page, route) {
  await page.goto(route, { waitUntil: "domcontentloaded" });
  await expect(page.locator("main")).toBeVisible();
  await page.waitForLoadState("networkidle");
}

async function visibleAsideWidths(page) {
  return page.locator("aside").evaluateAll((els) =>
    els
      .filter((el) => {
        const r = el.getBoundingClientRect();
        const st = getComputedStyle(el);
        return (
          st.display !== "none" &&
          st.visibility !== "hidden" &&
          r.width > 0 &&
          r.height > 0
        );
      })
      .map((el) => el.getBoundingClientRect().width),
  );
}

test.describe("Responsive app shell measurements", () => {
  for (const size of SIZES) {
    for (const route of ROUTES) {
      test(`${size.width}x${size.height} ${route}`, async ({ page }) => {
        await page.setViewportSize({ width: size.width, height: size.height });
        await openRoute(page, route);

        const mainWidth = (await page.locator("main").boundingBox()).width;
        if (size.minMainRatio)
          expect(mainWidth).toBeGreaterThanOrEqual(
            size.width * size.minMainRatio,
          );
        if (size.minMainPx)
          expect(mainWidth).toBeGreaterThanOrEqual(size.minMainPx);

        if (size.width < 1280) {
          for (const w of await visibleAsideWidths(page))
            expect(w).toBeLessThanOrEqual(80);
        }

        const overflow = await page.evaluate(() => ({
          scroll: document.documentElement.scrollWidth,
          inner: window.innerWidth,
        }));
        expect(overflow.scroll).toBe(overflow.inner);
      });
    }
  }
});

test.describe("Responsive app shell behaviour", () => {
  test("drawer opens, closes on Esc and returns focus to its button", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await openRoute(page, "/");
    const button = page.getByTestId("mobile-menu-button");
    await button.click();
    await expect(page.getByTestId("mobile-drawer")).toBeVisible();
    await expect(page.getByTestId("mobile-drawer-close")).toBeFocused();
    await page.keyboard.press("Escape");
    await expect(page.getByTestId("mobile-drawer")).toBeHidden();
    await expect(button).toBeFocused();
  });

  test("1024px: expanding opens an overlay without pushing the content", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await openRoute(page, "/");
    const before = (await page.locator("main").boundingBox()).width;
    await page.getByTestId("sidebar-toggle").click();
    await expect
      .poll(async () => Math.max(...(await visibleAsideWidths(page))))
      .toBeGreaterThan(200);
    expect((await page.locator("main").boundingBox()).width).toBe(before);
    await page.keyboard.press("Escape");
    await expect
      .poll(async () => Math.max(...(await visibleAsideWidths(page))))
      .toBeLessThanOrEqual(80);
  });

  test("1280px: collapse choice survives a reload", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await openRoute(page, "/");
    const widest = async () => Math.max(...(await visibleAsideWidths(page)));
    await expect.poll(widest).toBeLessThanOrEqual(256);
    const startedOpen = (await widest()) > 80;
    await page.getByTestId("sidebar-toggle").click();
    await expect.poll(async () => (await widest()) > 80).toBe(!startedOpen);
    await page.reload({ waitUntil: "domcontentloaded" });
    await expect(page.locator("main")).toBeVisible();
    await expect.poll(async () => (await widest()) > 80).toBe(!startedOpen);
    await page.getByTestId("sidebar-toggle").click();
    await expect.poll(async () => (await widest()) > 80).toBe(startedOpen);
  });
});
