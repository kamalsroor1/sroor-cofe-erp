import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  stubPrint,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 1: POS cash sale with weighted item and change", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    // Only run on desktop viewport
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("open shift -> sell regular & weighted items -> pay cash with change -> verify stock, shift cash & receipt", async ({
    page,
    request,
    context,
  }) => {
    // BUG-01: ShiftService::calculateShiftTotals() double-counts invoice cash in expected_cash_balance
    // because Payment::whereNotNull('customer_id') includes invoice-tied payments in addition to cash sales
    // file: backend/app/Services/ShiftService.php:129-138 (documented in docs/handoff/antigravity-e2e-docs-report.md)
    test.fail(
      true,
      "BUG-01: ShiftService double-counts cash invoice payments in expected_cash_balance",
    );

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
    const cashSalesBefore = parseFloat(
      shiftResBefore.body?.metrics?.total_cash_sales || "0",
    );
    const expectedCashBefore = parseFloat(
      shiftResBefore.body?.metrics?.expected_cash_balance || "500",
    );

    // 2. Create 2 test items: regular item & weighted item
    const itemRegular = await createTestItem(request, {
      name: "قهوة اسبريسو حبوب",
      cost: "50.000",
      price: "100.000",
      stock: "20.000",
      is_weighted: false,
      unit: "قطعة",
    });

    const itemWeighted = await createTestItem(request, {
      name: "بن برازيلي مطحون وزن",
      cost: "100.000",
      price: "200.000",
      stock: "10.000",
      is_weighted: true,
      unit: "كجم",
    });

    const stockRegularBefore = parseFloat(
      itemRegular.current_stock || "20.000",
    );
    const stockWeightedBefore = parseFloat(
      itemWeighted.current_stock || "10.000",
    );

    // 3. Perform POS cash sale:
    // Sell 1.000 of itemRegular (100.000) + 0.250 of itemWeighted (50.000) = Net 150.000
    // Tendered: 200.000 cash -> Change: 50.000
    const invoicePayload = {
      store_id: 1,
      payment_type: "cash",
      payment_method: "cash",
      paid_amount: "150.000",
      payments: [{ method: "cash", amount: "200.000" }],
      items: [
        {
          item_id: itemRegular.id,
          quantity: 1.0,
          unit_price: 100.0,
        },
        {
          item_id: itemWeighted.id,
          quantity: 0.25,
          unit_price: 200.0,
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
    expect(parseFloat(invoice.net_total || invoice.net_amount)).toBeCloseTo(
      150.0,
      3,
    );
    expect(parseFloat(invoice.change_amount)).toBeCloseTo(50.0, 3);

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

    const stockRegularAfter = parseFloat(
      refreshedItem1.body.data.current_stock,
    );
    const stockWeightedAfter = parseFloat(
      refreshedItem2.body.data.current_stock,
    );

    expect(stockRegularAfter).toBeCloseTo(stockRegularBefore - 1.0, 3);
    expect(stockWeightedAfter).toBeCloseTo(stockWeightedBefore - 0.25, 3);

    // 5. Verify Server State: Shift cash sales & expected cash balance increased
    const shiftResAfter = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      { storeId: 1 },
    );
    const cashSalesAfter = parseFloat(
      shiftResAfter.body?.metrics?.total_cash_sales,
    );
    const expectedCashAfter = parseFloat(
      shiftResAfter.body?.metrics?.expected_cash_balance,
    );

    expect(cashSalesAfter).toBeCloseTo(cashSalesBefore + 150.0, 3);
    expect(expectedCashAfter).toBeCloseTo(expectedCashBefore + 150.0, 3);

    // 6. UI Verification: Receipt is printable and thermal receipt view renders
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

    // Trigger print button and verify print call
    const printBtn = page
      .locator('button:has-text("طباعة الفاتورة"), button:has-text("طباعة")')
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
});
