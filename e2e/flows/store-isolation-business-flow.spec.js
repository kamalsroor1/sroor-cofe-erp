import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestStore,
  createTestItem,
  createTestUser,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 9: Store isolation and access boundary verification", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("cashier of store A cannot see or operate on store B in POS (403 store_access_denied)", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a separate isolated branch Store B
    const storeB = await createTestStore(request, {
      name: "فرع الإسكندرية المنفصل",
    });
    expect(storeB.id).toBeDefined();

    // 2. Create a test item associated with Store B
    const itemB = await createTestItem(request, {
      name: "صنف فرع الإسكندرية فقط",
      storeId: storeB.id,
      stock: "25.000",
    });

    // 3. Create a Cashier user assigned to Store 1 only
    const cashierA = await createTestUser(request, {
      role: "cashier",
      default_store_id: 1,
    });
    expect(cashierA.token).toBeTruthy();

    // 4. Verification 1: Cashier A accessing Store 1 succeeds
    const store1Shift = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      {
        token: cashierA.token,
        storeId: 1,
      },
    );
    expect([200, 404]).toContain(store1Shift.status);

    // 5. Security Boundary: Cashier A accessing Store B shift gets 403 store_access_denied
    const unauthorizedShift = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      {
        token: cashierA.token,
        storeId: storeB.id,
      },
    );
    expect(unauthorizedShift.status).toBe(403);
    expect(JSON.stringify(unauthorizedShift.body)).toMatch(
      /store_access_denied|unauthorized/i,
    );

    // 6. Security Boundary: Cashier A creating invoice in Store B gets 403
    const unauthorizedInvoice = await apiCall(
      request,
      "POST",
      "/api/v1/invoices",
      {
        token: cashierA.token,
        storeId: storeB.id,
        data: {
          store_id: storeB.id,
          payment_type: "cash",
          payment_method: "cash",
          paid_amount: "100.000",
          items: [
            {
              item_id: itemB.id,
              quantity: 1.0,
              unit_price: 100.0,
            },
          ],
        },
      },
    );
    expect(unauthorizedInvoice.status).toBe(403);

    // 7. UI Verification: Stores view loads cleanly
    await page.goto("/stores", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText(storeB.name);

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
