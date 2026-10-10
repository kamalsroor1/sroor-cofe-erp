import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 10: Mobile POS cash sale on 390px screen", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    // Only run on mobile project (Pixel 7 / 390px)
    test.skip(testInfo.project.name !== "mobile", "Mobile project flow only");
  });

  test("mobile 390px POS cash sale end-to-end", async ({ page, request }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Ensure shift is open
    const shift = await ensureShiftOpen(request, 1, "500.000");
    expect(shift).toBeTruthy();

    // 2. Create test item
    const item = await createTestItem(request, {
      name: "كابتشينو موبايل كاش",
      price: "120.000",
      cost: "50.000",
      stock: "30.000",
    });

    const stockBefore = parseFloat(item.current_stock || "30.000");

    // 3. Navigate to POS on mobile (390px)
    await page.goto("/pos", { waitUntil: "networkidle" });
    await expect(page.locator("body")).toBeVisible();

    // 4. Perform cash sale via API / UI
    const invoiceRes = await apiCall(request, "POST", "/api/v1/invoices", {
      storeId: 1,
      data: {
        store_id: 1,
        payment_type: "cash",
        payment_method: "cash",
        paid_amount: "120.000",
        items: [
          {
            item_id: item.id,
            quantity: 1.0,
            unit_price: 120.0,
          },
        ],
      },
    });

    expect(invoiceRes.status).toBe(201);
    expect(invoiceRes.body.success).toBe(true);

    const invoice = invoiceRes.body.data;
    expect(parseFloat(invoice.net_total || invoice.net_amount)).toBeCloseTo(
      120.0,
      3,
    );

    // 5. Server State Verification: Stock decreased by 1
    const refreshedItem = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const stockAfter = parseFloat(refreshedItem.body.data.current_stock);
    expect(stockAfter).toBeCloseTo(stockBefore - 1.0, 3);

    // 6. UI Verification: Mobile invoice show
    await page.goto(`/invoices/${invoice.id}`, { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText(invoice.invoice_number);

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
