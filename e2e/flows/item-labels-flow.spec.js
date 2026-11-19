// SHOP-04: barcode / price label printing from the items list.
//  - select two items, 3 copies each -> the print page renders 6 labels (each with a barcode);
//  - an item without a code is skipped with a warning;
//  - the print page reloads from session storage and window.print() is called by the Print button.
//
// The items list is mocked with page.route, so nothing is written to the local DB. Selectors use
// data-testid / roles, never translated text.
import { test, expect } from "@playwright/test";

const ITEMS = [
  {
    id: 992401,
    name: "E2E Label Beans",
    code: "5901234123457",
    unit: "piece",
    is_weighted: false,
    selling_price: "125.500",
    wholesale_price: "120.000",
    current_stock: "10.000",
    min_stock_level: "0.000",
    is_active: true,
    is_low_stock: false,
    can_be_deleted: true,
    deletion_blockers: [],
  },
  {
    id: 992402,
    name: "E2E Label Cups",
    code: "CUP-12",
    unit: "piece",
    is_weighted: false,
    selling_price: "15.000",
    wholesale_price: "14.000",
    current_stock: "40.000",
    min_stock_level: "0.000",
    is_active: true,
    is_low_stock: false,
    can_be_deleted: true,
    deletion_blockers: [],
  },
  {
    id: 992403,
    name: "E2E Label No Code",
    code: "",
    unit: "piece",
    is_weighted: false,
    selling_price: "5.000",
    wholesale_price: "5.000",
    current_stock: "1.000",
    min_stock_level: "0.000",
    is_active: true,
    is_low_stock: false,
    can_be_deleted: true,
    deletion_blockers: [],
  },
];

async function mockItems(page) {
  await page.route(/\/api\/v1\/items(\?.*)?$/, (route) => {
    if (route.request().method() !== "GET") return route.fallback();
    return route.fulfill({
      json: {
        success: true,
        data: ITEMS,
        summary: { total_items: 3, low_stock_count: 0, total_stock_value: 0 },
        categories: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 },
      },
    });
  });
}

const selectTestId = (page) =>
  (page.viewportSize()?.width ?? 1920) < 768
    ? "item-select-mobile"
    : "item-select";

test.describe("SHOP-04: item labels", () => {
  test("two items x 3 copies -> 6 labels on the print page", async ({
    page,
  }) => {
    await mockItems(page);
    await page.addInitScript(() => {
      window.__printCalls = 0;
      window.print = () => {
        window.__printCalls += 1;
      };
    });

    await page.goto("/items", { waitUntil: "networkidle" });
    const boxes = page.getByTestId(selectTestId(page));
    await expect(boxes).toHaveCount(3);
    await boxes.nth(0).check();
    await boxes.nth(1).check();

    await expect(page.getByTestId("items-selection-bar")).toBeVisible();
    await page.getByTestId("items-print-labels").click();

    const modal = page.getByTestId("labels-modal");
    await expect(modal).toBeVisible();
    const copies = modal.getByTestId("labels-copies");
    await expect(copies).toHaveCount(2);
    for (let i = 0; i < 2; i++) {
      await copies.nth(i).fill("3");
      await copies.nth(i).blur();
    }
    await page.getByTestId("labels-continue").click();

    await expect(page).toHaveURL(/\/items\/labels\/print$/);
    await expect(page.getByTestId("item-label")).toHaveCount(6);
    await expect(page.getByTestId("label-barcode")).toHaveCount(6);
    await expect(
      page.locator('[data-testid="label-barcode"][data-symbology="ean13"]'),
    ).toHaveCount(3);
    await expect(
      page.locator('[data-testid="label-barcode"][data-symbology="code128"]'),
    ).toHaveCount(3);

    // The job survives a reload of the print page.
    await page.reload({ waitUntil: "networkidle" });
    await expect(page.getByTestId("item-label")).toHaveCount(6);

    await page.getByTestId("labels-print").click();
    expect(await page.evaluate(() => window.__printCalls)).toBe(1);
  });

  test("an item without a code is skipped with a warning", async ({ page }) => {
    await mockItems(page);
    await page.goto("/items", { waitUntil: "networkidle" });
    const boxes = page.getByTestId(selectTestId(page));
    await boxes.nth(1).check();
    await boxes.nth(2).check();
    await page.getByTestId("items-print-labels").click();

    await expect(page.getByTestId("labels-modal").getByTestId("labels-skipped")).toBeVisible();
    await page.getByTestId("labels-continue").click();

    await expect(page.getByTestId("item-label")).toHaveCount(1);
    await expect(page.getByTestId("labels-skipped")).toBeVisible();
  });

  test("opening the print page without a job shows the empty state", async ({
    page,
  }) => {
    await page.goto("/items/labels/print", { waitUntil: "networkidle" });
    await expect(page.getByTestId("labels-empty")).toBeVisible();
    await expect(page.getByTestId("labels-print")).toBeDisabled();
  });
});
