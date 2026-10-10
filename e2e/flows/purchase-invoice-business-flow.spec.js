import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestSupplier,
  createTestItem,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 5: Purchase invoice, stock, supplier balance and WAC update", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("purchase invoice -> stock increased, supplier balance increased, WAC recalculated", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a test supplier
    const supplier = await createTestSupplier(request, {
      name: "مورد حبوب القهوة المركزي",
    });
    expect(supplier.id).toBeDefined();

    const supplierInitialRes = await apiCall(
      request,
      "GET",
      `/api/v1/suppliers/${supplier.id}`,
    );
    const initialSupplierBal = parseFloat(
      supplierInitialRes.body?.data?.current_balance || "0",
    );

    // 2. Create test item with known stock and cost:
    // 10 units @ cost 100.000
    const item = await createTestItem(request, {
      name: "حبوب كولومبي ممتازة",
      cost: "100.000",
      price: "180.000",
      stock: "10.000",
    });

    const stockBefore = parseFloat(item.current_stock || "10.000");

    // 3. Post a Purchase Invoice:
    // Buying 10 units @ unit_cost 120.000 (total: 1200.000), unpaid (paid_amount: 0)
    const today = new Date().toISOString().split("T")[0];
    const purchaseRes = await apiCall(request, "POST", "/api/v1/purchases", {
      storeId: 1,
      data: {
        supplier_id: supplier.id,
        purchase_date: today,
        paid_amount: "0.000",
        items: [
          {
            item_id: item.id,
            quantity: 10.0,
            unit_cost: 120.0,
          },
        ],
        notes: "فاتورة توريد بضاعة تجريبية E2E",
      },
    });

    expect(purchaseRes.status).toBe(201);
    expect(purchaseRes.body.success).toBe(true);

    const purchase = purchaseRes.body.data;
    expect(parseFloat(purchase.net_total)).toBeCloseTo(1200.0, 3);

    // 4. Server State Verification: Stock increased from 10 to 20
    const refreshedItemRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const refreshedItem = refreshedItemRes.body.data;
    const stockAfter = parseFloat(refreshedItem.current_stock);
    expect(stockAfter).toBeCloseTo(stockBefore + 10.0, 3);

    // 5. Server State Verification: Supplier balance increased by 1200.000
    const refreshedSupplierRes = await apiCall(
      request,
      "GET",
      `/api/v1/suppliers/${supplier.id}`,
    );
    const supplierBalAfter = parseFloat(
      refreshedSupplierRes.body.data.current_balance,
    );
    expect(supplierBalAfter).toBeCloseTo(initialSupplierBal + 1200.0, 3);

    // 6. Server State Verification: Weighted Average Cost / Cost updated
    // Item cost_price is updated to landed unit cost (120.000); if API exposes weighted_avg_cost it is 110.000
    if (refreshedItem.weighted_avg_cost !== undefined) {
      expect(parseFloat(refreshedItem.weighted_avg_cost)).toBeCloseTo(110.0, 1);
    } else {
      expect(parseFloat(refreshedItem.cost_price)).toBeCloseTo(120.0, 1);
    }

    // 7. UI Verification: View purchases ledger
    await page.goto("/purchases", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText("المشتريات");

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
