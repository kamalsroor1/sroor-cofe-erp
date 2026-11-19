// PLAN-AUDIT (2026-11-19): what a shop sees when it hits a plan limit.
//  - POS: the monthly invoice cap banner (near / reached) and the pre-checkout guard: at the cap the
//    checkout shows the upgrade alert and sends NO sale to the server;
//  - dashboard: the plan-usage widget turns "full" and offers the upgrade button;
//  - any 403 subscription.limit_reached answer opens the upgrade alert, whose button goes to /billing.
//
// The plan state is mocked with page.route (the server side of every limit is proven by PHPUnit:
// tests/Feature/Entitlements/LimitEnforcementMatrixTest.php and LimitReachedDailyWorkTest.php), so
// nothing is written to the local DB. Local server only (playwright.config.js). Selectors use
// data-testid / roles, never translated text.
import { test, expect } from "@playwright/test";
import { isPhoneViewport } from "../utils/pos-viewport.js";

const ITEM = {
  id: 991901,
  name: "E2E Limit Espresso",
  code: "E2E-LIMIT-1",
  unit: "piece",
  is_weighted: false,
  selling_price: "50.000",
  price_retail: "50.000",
  price_wholesale: "50.000",
  min_selling_price: "50.000",
  current_stock: "40.000",
  is_pos_pinned: true,
  pos_sales_count: 1,
};

const limitStatus = (used, limit) => ({
  limit,
  used,
  remaining: Math.max(0, limit - used),
  percent: Math.ceil((used * 100) / limit),
  reached: used >= limit,
  resets_at: "2026-12-01T00:00:00+02:00",
});

const LIMIT_REACHED = (resource, max) => ({
  success: false,
  message: `limit ${resource} ${max}`,
  error_code: "subscription.limit_reached",
  details: { limit: resource, max },
});

async function mockPos(page, status, onSale) {
  await page.addInitScript(() => {
    Object.keys(localStorage)
      .filter((k) => k.startsWith("pos_multi_orders"))
      .forEach((k) => localStorage.removeItem(k));
  });
  await page.route("**/api/v1/pos/invoice-limit*", (route) =>
    route.fulfill({ json: { success: true, data: status } }),
  );
  await page.route("**/api/v1/pos/items*", (route) =>
    route.fulfill({
      json: {
        success: true,
        data: [ITEM],
        meta: {
          current_page: 1,
          last_page: 1,
          total: 1,
          counts: { total: 1, categories: [] },
        },
      },
    }),
  );
  await page.route("**/api/v1/categories*", (route) =>
    route.fulfill({ json: { success: true, data: [], total_items_count: 1 } }),
  );
  await page.route("**/api/v1/shifts/current*", (route) =>
    route.fulfill({
      json: {
        success: true,
        requires_shift: false,
        active_shift: null,
        metrics: null,
      },
    }),
  );
  for (const url of ["**/api/v1/invoices", "**/api/v1/pos/checkout"]) {
    await page.route(url, (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      onSale(route.request().postDataJSON());
      return route.fulfill({
        status: 403,
        json: LIMIT_REACHED("invoices_month", status.limit),
      });
    });
  }
}

test.describe("PLAN-AUDIT: plan limit UX", () => {
  test("POS at the monthly invoice cap: red banner, checkout refused before any request", async ({
    page,
  }) => {
    test.skip(
      isPhoneViewport(page),
      "Desktop POS layout; the compact POS keeps checkout in a BottomSheet",
    );
    const sales = [];
    await mockPos(page, limitStatus(150, 150), (payload) =>
      sales.push(payload),
    );

    await page.goto("/pos", { waitUntil: "networkidle" });
    const banner = page.getByTestId("pos-invoice-limit-banner");
    await expect(banner).toBeVisible();
    await expect(banner).toHaveAttribute("data-state", "reached");

    await page.getByText(ITEM.name, { exact: true }).first().click();
    await page.getByTestId("pos-checkout-submit").click();

    // The upgrade alert, and no sale left the browser.
    await expect(page.locator(".swal2-popup")).toBeVisible();
    expect(sales).toHaveLength(0);
  });

  test("POS near the cap: amber banner and the sale is still attempted", async ({
    page,
  }) => {
    test.skip(
      isPhoneViewport(page),
      "Desktop POS layout; the compact POS keeps checkout in a BottomSheet",
    );
    const sales = [];
    await mockPos(page, limitStatus(130, 150), (payload) =>
      sales.push(payload),
    );

    await page.goto("/pos", { waitUntil: "networkidle" });
    await expect(page.getByTestId("pos-invoice-limit-banner")).toHaveAttribute(
      "data-state",
      "near",
    );

    await page.getByText(ITEM.name, { exact: true }).first().click();
    await page.getByTestId("pos-checkout-submit").click();

    // Below the cap the client never blocks: the server is the authority (here it refuses).
    await expect.poll(() => sales.length).toBe(1);
    await expect(page.locator(".swal2-popup")).toBeVisible();
  });

  test("POS under 80% of the cap shows no banner", async ({ page }) => {
    test.skip(
      isPhoneViewport(page),
      "Desktop POS layout; the compact POS keeps checkout in a BottomSheet",
    );
    await mockPos(page, limitStatus(10, 150), () => {});

    await page.goto("/pos", { waitUntil: "networkidle" });
    await page.getByTestId("pos-checkout-submit").waitFor({ state: "visible" });
    await expect(page.getByTestId("pos-invoice-limit-banner")).toHaveCount(0);
  });

  test("dashboard widget turns full at 100% of items and offers the upgrade", async ({
    page,
  }) => {
    await page.route("**/api/v1/billing/subscription*", (route) =>
      route.fulfill({
        json: {
          success: true,
          data: {
            usage: [
              {
                resource: "items",
                used: 100,
                limit: 100,
                percent: 100,
                unit: "count",
                resets_at: null,
              },
              {
                resource: "customers",
                used: 12,
                limit: 50,
                percent: 24,
                unit: "count",
                resets_at: null,
              },
              // An allowance at 1 of 1 is normal, never a warning.
              {
                resource: "stores",
                used: 1,
                limit: 1,
                percent: 100,
                unit: "count",
                resets_at: null,
              },
              // Not included (limit 0) and unlimited (null): no bar.
              {
                resource: "vans",
                used: 0,
                limit: 0,
                percent: 100,
                unit: "count",
                resets_at: null,
              },
              {
                resource: "suppliers",
                used: 3,
                limit: null,
                percent: null,
                unit: "count",
                resets_at: null,
              },
            ],
          },
        },
      }),
    );

    await page.goto("/", { waitUntil: "networkidle" });
    const widget = page.getByTestId("plan-usage-widget");
    await expect(widget).toBeVisible();
    await expect(widget).toHaveAttribute("data-level", "full");
    await expect(widget.locator('[data-resource="items"]')).toBeVisible();
    await expect(widget.locator('[data-resource="stores"]')).toBeVisible();
    await expect(widget.locator('[data-resource="vans"]')).toHaveCount(0);
    await expect(widget.locator('[data-resource="suppliers"]')).toHaveCount(0);
    await expect(page.getByTestId("plan-usage-upgrade")).toBeVisible();
  });

  test("a limit_reached answer opens the upgrade alert and its button goes to billing", async ({
    page,
  }) => {
    // Any API answer with this contract goes through the same axios interceptor; the items list
    // load is the simplest request to make answer it.
    await page.route("**/api/v1/items?*", (route) =>
      route.fulfill({ status: 403, json: LIMIT_REACHED("items", 100) }),
    );

    await page.goto("/items");
    const popup = page.locator(".swal2-popup");
    await expect(popup).toBeVisible();
    await expect(popup).toContainText("items 100");

    await popup.locator(".swal2-confirm").click();
    await expect(page).toHaveURL(/\/billing/);
  });
});
