import { test, expect } from "@playwright/test";

// UI-06: list pages stay readable below 1920px. Measurements only (no screenshots, no translated text):
// no horizontal page scroll, numbers never clipped, table headers short, metric strip compact, header
// actions collapse on phones, settings sections become tabs below 1280px, return summary stacks below 1280px.
test.beforeEach(({ browserName }, testInfo) => {
  test.skip(
    testInfo.project.name !== "desktop",
    "Sets its own viewports; runs once in the desktop project.",
  );
});

const SIZES = [
  { width: 375, height: 812 },
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1366, height: 768 },
];
const ROUTES = [
  "/invoices",
  "/items",
  "/customers",
  "/daily-journal",
  "/returns/create",
  "/reports",
  "/settings",
  "/roles",
];

// Settings and roles are admin-only: a quick-login session is refused there, so the two payloads are stubbed.
// These specs measure layout, not data.
async function stubAdminApis(page) {
  const json = (data) => ({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify(data),
  });
  await page.route(/\/api\/v1\/settings(\?.*)?$/, (route) =>
    route.fulfill(json({ success: true, settings: { company_name: "Demo" } })),
  );
  await page.route(/\/api\/v1\/roles(\?.*)?$/, (route) =>
    route.fulfill(
      json({
        success: true,
        data: {
          roles: ["admin", "cashier", "storekeeper", "accountant"].map(
            (name, index) => ({
              id: index + 1,
              name,
              label: name,
              permissions_count: 3,
              permissions: ["invoices.view"],
            }),
          ),
          permission_modules: {
            sales: {
              title: "Sales",
              icon: "shopping-cart",
              permissions: {
                "invoices.view": "View",
                "invoices.create": "Create",
              },
            },
          },
          selected_role: {
            id: 2,
            name: "cashier",
            permissions: ["invoices.view"],
          },
        },
      }),
    ),
  );
}

async function open(page, route, size) {
  await stubAdminApis(page);
  await page.setViewportSize(size);
  await page.goto(route, { waitUntil: "domcontentloaded" });
  await expect(page.locator("main").first()).toBeVisible();
  await page.waitForLoadState("networkidle");
  await expect(page.locator("main .animate-pulse")).toHaveCount(0, {
    timeout: 15000,
  });
}

test.describe("No clipping on list pages", () => {
  for (const size of SIZES) {
    for (const route of ROUTES) {
      test(`${size.width}x${size.height} ${route}`, async ({ page }) => {
        await open(page, route, size);

        const result = await page.evaluate(() => {
          const de = document.documentElement;
          // An amount/quantity: digits, separators and at most a short unit/currency word ("1,250.500 EGP").
          const looksNumeric = (text) =>
            /^[\s\d.,\u066b\u066c-]+(\S{1,8})?$/u.test(text.trim());
          const lineClipsNumber = (el) =>
            el.scrollWidth > el.clientWidth + 1 && looksNumeric(el.textContent);
          return {
            pageOverflow: de.scrollWidth - de.clientWidth,
            clippedNumbers: [...document.querySelectorAll("main .truncate")]
              .filter(lineClipsNumber)
              .map((el) => el.textContent.trim().slice(0, 40)),
            clippedMetricValues: [
              ...document.querySelectorAll("[data-metric-value]"),
            ]
              .filter((el) => {
                const card = el
                  .closest("[data-metric-card]")
                  .getBoundingClientRect();
                const box = el.getBoundingClientRect();
                return (
                  box.left < card.left - 1 ||
                  box.right > card.right + 1 ||
                  el.scrollWidth > el.clientWidth + 1
                );
              })
              .map((el) => el.textContent.trim()),
            tallHeaders: [...document.querySelectorAll("th")]
              .filter((th) => th.offsetParent !== null)
              .filter((th) => {
                const style = getComputedStyle(th);
                const lines =
                  (th.getBoundingClientRect().height -
                    parseFloat(style.paddingTop) -
                    parseFloat(style.paddingBottom)) /
                  (parseFloat(style.lineHeight) ||
                    parseFloat(style.fontSize) * 1.5);
                return lines > 2.2;
              })
              .map((th) => th.textContent.trim()),
          };
        });

        expect(result.pageOverflow).toBe(0);
        expect(result.clippedNumbers).toEqual([]);
        expect(result.clippedMetricValues).toEqual([]);
        expect(result.tallHeaders).toEqual([]);
      });
    }
  }

  test("1366x768 content area is at least 1080px wide", async ({ page }) => {
    await open(page, "/items", { width: 1366, height: 768 });
    expect(
      (await page.locator("main").first().boundingBox()).width,
    ).toBeGreaterThanOrEqual(1080);
  });
});

test.describe("Items table", () => {
  for (const size of SIZES.filter((s) => s.width >= 768)) {
    test(`${size.width}px: codes and badges stay on one line, filters stay reachable`, async ({
      page,
    }) => {
      await open(page, "/items", size);

      for (const testId of ["item-code", "item-status"]) {
        const cells = page.getByTestId(testId);
        await expect(cells.first()).toBeVisible();
        const heights = await cells.evaluateAll((els) =>
          els.map((el) => {
            const style = getComputedStyle(el);
            const lineHeight =
              parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.5;
            const range = document.createRange();
            range.selectNodeContents(el);
            return range.getBoundingClientRect().height / lineHeight;
          }),
        );
        for (const lines of heights) expect(lines).toBeLessThan(1.5);
      }

      const filters = page.getByTestId("items-stock-filters");
      const box = await filters.boundingBox();
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(size.width);
      const scrollable = await filters.evaluate(
        (el) => el.scrollWidth > el.clientWidth,
      );
      if (!scrollable) {
        const buttons = await filters.locator("button").evaluateAll((els) =>
          els.map((el) => {
            const r = el.getBoundingClientRect();
            return { left: r.left, right: r.right };
          }),
        );
        for (const b of buttons) {
          expect(b.left).toBeGreaterThanOrEqual(0);
          expect(b.right).toBeLessThanOrEqual(size.width);
        }
      }
    });
  }
});

test.describe("Metric strip and page header", () => {
  test("1366: strip cards are at most 72px tall", async ({ page }) => {
    for (const route of ["/invoices", "/items", "/customers"]) {
      await open(page, route, { width: 1366, height: 768 });
      const heights = await page
        .getByTestId("metric-strip")
        .first()
        .locator("[data-metric-card]")
        .evaluateAll((els) =>
          els.map((el) => el.getBoundingClientRect().height),
        );
      expect(heights.length).toBeGreaterThan(0);
      for (const h of heights) expect(h).toBeLessThanOrEqual(72);
    }
  });

  test("375: strip is one swipeable row and the first invoice shows within a screen and a half", async ({
    page,
  }) => {
    await open(page, "/invoices", { width: 375, height: 812 });
    const strip = page.getByTestId("metric-strip").first();
    const tops = await strip
      .locator("[data-metric-card]")
      .evaluateAll((els) =>
        els.map((el) => Math.round(el.getBoundingClientRect().top)),
      );
    expect(new Set(tops).size).toBe(1);
    expect(await strip.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(
      true,
    );

    const firstRow = page.getByTestId("data-table-card").first();
    await expect(firstRow).toBeVisible();
    const box = await firstRow.boundingBox();
    expect(box.y).toBeLessThanOrEqual(812);
    expect(box.y + Math.min(box.height, 120)).toBeLessThanOrEqual(812 * 1.5);
  });

  test("375: invoices header keeps one primary action plus an overflow menu", async ({
    page,
  }) => {
    await open(page, "/invoices", { width: 375, height: 812 });
    const actions = page.getByTestId("page-actions").first();
    await expect(actions.getByTestId("page-actions-more")).toBeVisible();
    await expect(actions.getByTestId("page-action-pos")).toBeVisible();
    await expect(actions.locator("button, a")).toHaveCount(2);
    const tops = await actions
      .locator("button, a")
      .evaluateAll((els) =>
        els.map((el) => Math.round(el.getBoundingClientRect().top)),
      );
    expect(new Set(tops).size).toBe(1);

    await actions.getByTestId("page-actions-more").click();
    const menu = page.getByTestId("dropdown-menu");
    await expect(menu).toBeVisible();
    await expect(menu.locator("button")).toHaveCount(4);
  });

  test("1024: journal header actions sit on one row below the title or beside it, never overlapping", async ({
    page,
  }) => {
    await open(page, "/daily-journal", { width: 1024, height: 768 });
    const rects = await page
      .getByTestId("page-header")
      .first()
      .evaluate((header) => {
        const title = header.querySelector("h1").getBoundingClientRect();
        const actions = header
          .querySelector("[data-testid=page-actions]")
          .getBoundingClientRect();
        const titleLines =
          title.height /
          (parseFloat(
            getComputedStyle(header.querySelector("h1")).lineHeight,
          ) || 32);
        return {
          overlap: !(
            actions.right <= title.left ||
            actions.left >= title.right ||
            actions.bottom <= title.top ||
            actions.top >= title.bottom
          ),
          titleLines,
        };
      });
    expect(rects.overlap).toBe(false);
    expect(rects.titleLines).toBeLessThan(1.6);
  });
});

test.describe("Settings and returns below 1280px", () => {
  test("settings sections are a tab row at 1024 and a side list at 1366", async ({
    page,
  }) => {
    await open(page, "/settings", { width: 1024, height: 768 });
    const tops = async () => ({
      first: Math.round(
        (await page.getByTestId("tab-general").boundingBox()).y,
      ),
      last: Math.round((await page.getByTestId("tab-device").boundingBox()).y),
    });
    const narrow = await tops();
    expect(narrow.first).toBe(narrow.last);

    await open(page, "/settings", { width: 1366, height: 768 });
    const wide = await tops();
    expect(wide.last).toBeGreaterThan(wide.first);
  });

  test("return summary stacks under the form at 1024 and sits beside it at 1366", async ({
    page,
  }) => {
    await open(page, "/returns/create", { width: 1024, height: 768 });
    const summary = page.getByTestId("return-summary");
    const main = await page.locator("main").first().boundingBox();
    const stacked = await summary.boundingBox();
    expect(stacked.width).toBeGreaterThan(main.width * 0.8);

    await open(page, "/returns/create", { width: 1366, height: 768 });
    const beside = await summary.boundingBox();
    expect(beside.width).toBeLessThanOrEqual(400);
  });
});
