import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestStore,
  createTestItem,
  createTestUser,
  toDecimal3,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 7: Stock transfer between stores and store access authorization", () => {
  let destinationStore;
  let item;
  let restrictedUser;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      items: [item],
      users: [restrictedUser?.user],
    });
  });

  test("transfer moves stock between stores, unauthorized store access returns 403 store_access_denied", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a secondary branch/store
    destinationStore = await createTestStore(request, {
      name: "فرع مدينة نصر تحويل",
    });
    expect(destinationStore.id).toBeDefined();

    // 2. Create a test item with initial stock of 50 in Store 1
    item = await createTestItem(request, {
      name: "بن حبوب كولومبي للتحويل E2E",
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
            quantity: "15.000",
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

    // Source store stock: 50 - 15 = 35.000
    const sourceQty = toDecimal3(sourceStockRes.body.data.current_stock);
    expect(sourceQty).toBe("35.000");

    // Destination store stock: 0 + 15 = 15.000 (explicitly verified)
    const destQty = toDecimal3(destStockRes.body.data.current_stock);
    expect(destQty).toBe("15.000");

    // 5. Security Check: User with transfers.create but without access to Store 1 gets 403 store_access_denied
    restrictedUser = await createTestUser(request, {
      role: "storekeeper", // storekeeper has transfers.create permission
      default_store_id: destinationStore.id,
    });

    const unauthorizedTransfer = await apiCall(
      request,
      "POST",
      "/api/v1/transfers",
      {
        token: restrictedUser.token,
        storeId: destinationStore.id,
        data: {
          from_store_id: 1, // Store 1 which user cannot access
          to_store_id: destinationStore.id,
          transfer_date: today,
          items: [{ item_id: item.id, quantity: "5.000" }],
        },
      },
    );

    expect(unauthorizedTransfer.status).toBe(403);
    expect(unauthorizedTransfer.body.error_code).toBe("store_access_denied");

    // 6. UI Verification: Stock Transfers view
    await page.goto("/stock-transfers", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator('[data-testid="transfers-table"]')).toBeVisible({
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
});
