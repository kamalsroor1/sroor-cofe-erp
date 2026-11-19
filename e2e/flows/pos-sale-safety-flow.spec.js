// SHOP-01: POS sale safety.
//  - adding a zero-stock item warns (negative stock allowed) or is refused (negative stock off);
//  - "clear cart" asks for confirmation when the cart has lines, and cancel keeps the cart;
//  - the cash drawer rule: a credit / card sale never kicks the drawer (helper level, no printer needed).
//
// POS data is mocked with page.route, so nothing is written to the local DB. Selectors use
// data-testid and SweetAlert2 classes; the stock messages are matched by the item name they carry
// (the new keys reach defaultTranslations.*.json only after `php artisan lang:export`).
import { test, expect } from "@playwright/test";
import { skipOnPhoneViewport } from "../utils/pos-viewport.js";
import { saleIncludesCash } from "../../backend/resources/js/helpers/posReceiptHtml.js";

const STOCKED = {
  id: 997001,
  name: "SHOP01 Stocked Mug",
  code: "SHOP01-STOCKED",
  unit: "piece",
  is_weighted: false,
  selling_price: "40.000",
  price_retail: "40.000",
  price_wholesale: "40.000",
  min_selling_price: "40.000",
  current_stock: "25.000",
  is_pos_pinned: true,
  pos_sales_count: 5,
};

const EMPTY = {
  ...STOCKED,
  id: 997002,
  name: "SHOP01 Empty Beans",
  code: "SHOP01-EMPTY",
  current_stock: "0.000",
  pos_sales_count: 4,
};

async function mockPos(page, { allowNegativeStock }) {
  await page.addInitScript(() => {
    Object.keys(localStorage)
      .filter((k) => k.startsWith("pos_multi_orders"))
      .forEach((k) => localStorage.removeItem(k));
  });
  await page.route("**/api/v1/pos/items*", (route) =>
    route.fulfill({
      json: {
        success: true,
        data: [STOCKED, EMPTY],
        meta: {
          current_page: 1,
          last_page: 1,
          total: 2,
          counts: { total: 2, categories: [] },
        },
      },
    }),
  );
  await page.route("**/api/v1/categories*", (route) =>
    route.fulfill({ json: { success: true, data: [], total_items_count: 2 } }),
  );
  await page.route("**/api/v1/pos/bootstrap*", (route) =>
    route.fulfill({
      json: {
        success: true,
        data: {
          pos_settings: { effective_max_discount_percent: "10.000" },
          allow_negative_stock: allowNegativeStock,
        },
      },
    }),
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
}

async function openPos(page) {
  await page.goto("/pos", { waitUntil: "networkidle" });
  await page
    .getByTestId("pos-checkout-submit")
    .waitFor({ state: "visible", timeout: 15000 });
}

const cartRows = (page) => page.locator('[data-testid="pos-cart-row"]:visible');

test.describe("SHOP-01: POS sale safety", () => {
  skipOnPhoneViewport(test);

  test("zero-stock item is added with a warning when negative stock is allowed", async ({
    page,
  }) => {
    await mockPos(page, { allowNegativeStock: true });
    await openPos(page);

    await page.getByText(EMPTY.name, { exact: true }).first().click();

    await expect(
      page.locator(".swal2-popup").filter({ hasText: EMPTY.name }),
    ).toBeVisible();
    await expect(cartRows(page)).toHaveCount(1);
  });

  test("zero-stock item is refused when negative stock is off", async ({
    page,
  }) => {
    await mockPos(page, { allowNegativeStock: false });
    await openPos(page);

    await page.getByText(EMPTY.name, { exact: true }).first().click();

    await expect(
      page.locator(".swal2-popup").filter({ hasText: EMPTY.name }),
    ).toBeVisible();
    await expect(cartRows(page)).toHaveCount(0);

    // A stocked item still goes in silently.
    await page.getByText(STOCKED.name, { exact: true }).first().click();
    await expect(cartRows(page)).toHaveCount(1);
  });

  test("clearing a cart with lines asks first; cancel keeps the cart, confirm empties it", async ({
    page,
  }) => {
    await mockPos(page, { allowNegativeStock: true });
    await openPos(page);

    await page.getByText(STOCKED.name, { exact: true }).first().click();
    await expect(cartRows(page)).toHaveCount(1);

    await page.getByTestId("pos-clear-cart").click();
    const dialog = page.locator(".swal2-popup");
    await expect(dialog).toBeVisible();
    await dialog.locator(".swal2-cancel").click();
    await expect(dialog).toBeHidden();
    await expect(cartRows(page)).toHaveCount(1);

    await page.getByTestId("pos-clear-cart").click();
    await expect(dialog).toBeVisible();
    await dialog.locator(".swal2-confirm").click();
    await expect(cartRows(page)).toHaveCount(0);
  });

  test("cash drawer opens only when the saved sale took cash", () => {
    expect(
      saleIncludesCash({
        payment_type: "credit",
        payment_method: "cash",
        paid_amount: "0.000",
      }),
    ).toBe(false);
    expect(
      saleIncludesCash({
        payment_type: "cash",
        payment_method: "visa",
        paid_amount: "50.000",
      }),
    ).toBe(false);
    expect(
      saleIncludesCash({
        payment_type: "cash",
        payment_method: "cash",
        paid_amount: "50.000",
      }),
    ).toBe(true);
    expect(
      saleIncludesCash({
        payment_type: "cash",
        payments: [
          { payment_method: "visa", amount: "30.000" },
          { payment_method: "cash", amount: "20.000" },
        ],
      }),
    ).toBe(true);
    expect(
      saleIncludesCash({
        payment_type: "partial",
        payments: [{ payment_method: "instapay", amount: "20.000" }],
      }),
    ).toBe(false);
  });
});
