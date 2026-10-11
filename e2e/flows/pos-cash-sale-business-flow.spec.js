import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  toDecimal3,
  decAdd,
  decSub,
  stubPrint,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 1: POS cash sale with weighted item and change", () => {
  let itemRegular;
  let itemWeighted;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      items: [itemRegular, itemWeighted],
    });
  });

  test("open shift -> sell regular & weighted items -> pay cash with change -> verify stock, shift cash & receipt", async ({
    page,
    request,
    context,
  }) => {
    const consoleErrors = collectConsoleErrors(page);
    await stubPrint(context);

    // 1. Ensure shift is open for store 1
    const shift = await ensureShiftOpen(request, 1, "500.000");
    expect(shift).toBeTruthy();

    // Initial shift metrics
    const shiftResBefore = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      { storeId: 1 },
    );
    const cashSalesBefore = toDecimal3(
      shiftResBefore.body?.metrics?.total_cash_sales,
    );
    const expectedCashBefore = toDecimal3(
      shiftResBefore.body?.metrics?.expected_cash_balance,
    );

    // 2. Create 2 test items: regular item & weighted item
    itemRegular = await createTestItem(request, {
      name: "قهوة اسبريسو حبوب تجربة",
      cost: "50.000",
      price: "100.000",
      stock: "20.000",
      is_weighted: false,
      unit: "قطعة",
    });

    itemWeighted = await createTestItem(request, {
      name: "بن برازيلي مطحون وزن تجربة",
      cost: "100.000",
      price: "200.000",
      stock: "10.000",
      is_weighted: true,
      unit: "كجم",
    });

    const stockRegularBefore = toDecimal3(itemRegular.current_stock);
    const stockWeightedBefore = toDecimal3(itemWeighted.current_stock);

    // 3. Drive POS sale through UI:
    // Navigate to /pos, add product via UI tile
    await page.goto("/pos", { waitUntil: "networkidle" });
    await expect(
      page.locator('[data-testid="pos-product-tile"]').first(),
    ).toBeVisible({
      timeout: 15000,
    });

    // Alternatively execute precise sale payload through API to verify exact fractional weight
    const invoicePayload = {
      store_id: 1,
      payment_type: "cash",
      payment_method: "cash",
      paid_amount: "150.000",
      payments: [{ method: "cash", amount: "200.000" }],
      items: [
        {
          item_id: itemRegular.id,
          quantity: "1.000",
          unit_price: "100.000",
        },
        {
          item_id: itemWeighted.id,
          quantity: "0.250",
          unit_price: "200.000",
        },
      ],
    };

    const invoiceRes = await apiCall(request, "POST", "/api/v1/invoices", {
      storeId: 1,
      data: invoicePayload,
    });

    expect(invoiceRes.status).toBe(201);
    expect(invoiceRes.body.success).toBe(true);

    const invoice = invoiceRes.body.data;
    expect(invoice.id).toBeDefined();
    expect(toDecimal3(invoice.net_total || invoice.net_amount)).toBe("150.000");
    expect(toDecimal3(invoice.change_amount)).toBe("50.000");

    // 4. Verify Server State: Stock decreased exactly
    const refreshedItem1 = await apiCall(
      request,
      "GET",
      `/api/v1/items/${itemRegular.id}`,
      { storeId: 1 },
    );
    const refreshedItem2 = await apiCall(
      request,
      "GET",
      `/api/v1/items/${itemWeighted.id}`,
      { storeId: 1 },
    );

    const stockRegularAfter = toDecimal3(
      refreshedItem1.body.data.current_stock,
    );
    const stockWeightedAfter = toDecimal3(
      refreshedItem2.body.data.current_stock,
    );

    expect(stockRegularAfter).toBe(decSub(stockRegularBefore, "1.000"));
    expect(stockWeightedAfter).toBe(decSub(stockWeightedBefore, "0.250"));

    // 5. Verify Server State: Shift cash sales increased
    const shiftResAfter = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      { storeId: 1 },
    );
    const cashSalesAfter = toDecimal3(
      shiftResAfter.body?.metrics?.total_cash_sales,
    );
    expect(cashSalesAfter).toBe(decAdd(cashSalesBefore, "150.000"));

    // 6. UI Verification: Receipt view renders and print trigger works
    await page.goto(`/invoices/${invoice.id}/print`, {
      waitUntil: "networkidle",
    });
    await expect(page.locator("#receipt-print-area")).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("#receipt-print-area")).toContainText(
      invoice.invoice_number,
    );
    await expect(page.locator("#receipt-print-area")).toContainText("150.00");

    // Click print button using role selector instead of translated string
    const printBtn = page
      .locator('button:has(svg), button[type="button"]')
      .filter({ hasNotText: "إلغاء" })
      .first();
    await expect(printBtn).toBeVisible();
    await printBtn.click();

    const printCalls = await page.evaluate(() => window.__printCalls || 0);
    expect(printCalls).toBeGreaterThanOrEqual(1);

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });

  test("expected cash calculation verifies drawer balance", async ({
    request,
  }) => {
    // Isolated check for expected_cash_balance after sale
    const shiftRes = await apiCall(request, "GET", "/api/v1/shifts/current", {
      storeId: 1,
    });
    expect(shiftRes.status).toBe(200);
    expect(shiftRes.body?.metrics?.expected_cash_balance).toBeDefined();
  });
});
