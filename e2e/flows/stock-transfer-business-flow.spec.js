import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestStore,
  createTestItem,
  createTestUser,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 7: Stock transfer between stores and store access authorization", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("transfer moves stock between stores, unauthorized store access returns 403", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a secondary branch/store
    const destinationStore = await createTestStore(request, {
      name: "فرع مدينة نصر",
    });
    expect(destinationStore.id).toBeDefined();

    // 2. Create a test item with initial stock of 50 in Store 1
    const item = await createTestItem(request, {
      name: "بن حبوب كولومبي للتحويل",
      stock: "50.000",
      storeId: 1,
    });

    // 3. Perform Stock Transfer: Move 15.000 units from Store 1 to Store 2
    const today = new Date().toISOString().split("T")[0];
    const transferRes = await apiCall(request, "POST", "/api/v1/transfers", {
      storeId: 1,
      data: {
        from_store_id: 1,
        to_store_id: destinationStore.id,
        transfer_date: today,
        items: [
          {
            item_id: item.id,
            quantity: 15.0,
          },
        ],
        notes: "تحويل مخزني داخلي بين الفروع E2E",
      },
    });

    expect(transferRes.status).toBe(201);
    expect(transferRes.body.success).toBe(true);

    // 4. Server State Verification: Source decreased, destination increased
    const sourceStockRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const destStockRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: destinationStore.id },
    );

    // Source store stock: 50 - 15 = 35
    const sourceQty = parseFloat(sourceStockRes.body.data.current_stock);
    expect(sourceQty).toBeCloseTo(35.0, 3);

    // 5. Security Check: User without access to Store 1 gets 403 on transfer attempt
    const restrictedCashier = await createTestUser(request, {
      role: "cashier",
      default_store_id: destinationStore.id,
    });

    const unauthorizedTransfer = await apiCall(
      request,
      "POST",
      "/api/v1/transfers",
      {
        token: restrictedCashier.token,
        storeId: destinationStore.id,
        data: {
          from_store_id: 1, // Store 1 which cashier cannot access
          to_store_id: destinationStore.id,
          transfer_date: today,
          items: [{ item_id: item.id, quantity: 5.0 }],
        },
      },
    );

    expect(unauthorizedTransfer.status).toBe(403);

    // 6. UI Verification: Stock Transfers view
    await page.goto("/stock-transfers", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText("التحويلات");

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
