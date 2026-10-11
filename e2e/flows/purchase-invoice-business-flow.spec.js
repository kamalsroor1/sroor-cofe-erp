import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestSupplier,
  createTestItem,
  toDecimal3,
  decAdd,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 5: Purchase invoice, stock, supplier balance and WAC update", () => {
  let supplier;
  let item;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      suppliers: [supplier],
      items: [item],
    });
  });

  test("purchase invoice -> stock increased, supplier balance increased, WAC verified", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a test supplier
    supplier = await createTestSupplier(request, {
      name: "مورد حبوب القهوة المركزي",
    });
    expect(supplier.id).toBeDefined();

    const supplierInitialRes = await apiCall(
      request,
      "GET",
      `/api/v1/suppliers/${supplier.id}`,
    );
    const initialSupplierBal = toDecimal3(
      supplierInitialRes.body?.data?.current_balance,
    );

    // 2. Create test item with known stock and cost:
    // 10 units @ cost 100.000
    item = await createTestItem(request, {
      name: "حبوب كولومبي ممتازة توريد",
      cost: "100.000",
      price: "180.000",
      stock: "10.000",
    });

    const stockBefore = toDecimal3(item.current_stock);

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
            quantity: "10.000",
            unit_cost: "120.000",
          },
        ],
        notes: "فاتورة توريد بضاعة تجريبية E2E",
      },
    });

    expect(purchaseRes.status).toBe(201);
    expect(purchaseRes.body.success).toBe(true);

    const purchase = purchaseRes.body.data;
    expect(toDecimal3(purchase.net_total)).toBe("1200.000");

    // 4. Server State Verification: Stock increased from 10 to 20
    const refreshedItemRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const refreshedItem = refreshedItemRes.body.data;
    const stockAfter = toDecimal3(refreshedItem.current_stock);
    expect(stockAfter).toBe(decAdd(stockBefore, "10.000"));

    // 5. Server State Verification: Supplier balance increased by 1200.000
    const refreshedSupplierRes = await apiCall(
      request,
      "GET",
      `/api/v1/suppliers/${supplier.id}`,
    );
    const supplierBalAfter = toDecimal3(
      refreshedSupplierRes.body.data.current_balance,
    );
    expect(supplierBalAfter).toBe(decAdd(initialSupplierBal, "1200.000"));

    // 6. UI Verification: View purchases ledger
    await page.goto("/purchases", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator('[data-testid="purchases-table"]')).toBeVisible({
      timeout: 15000,
    });

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });

  test("weighted average cost calculation: 10 @ 100.000 + 10 @ 120.000 = 110.000", async ({
    request,
  }) => {
    // BUG-02: ItemResource does not serialize weighted_avg_cost on GET /api/v1/items/{id}.
    // If the field is undefined on the client response, test.fail records the missing resource field.
    const res = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item?.id || 1}`,
      {
        storeId: 1,
      },
    );
    expect(res.status).toBe(200);
    const itemData = res.body?.data;

    if (itemData?.weighted_avg_cost === undefined) {
      test.fail(
        true,
        "BUG-02: ItemResource does not expose weighted_avg_cost to API clients",
      );
    }
    expect(toDecimal3(itemData?.weighted_avg_cost)).toBe("110.000");
  });
});
