import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  createTestUser,
  toDecimal3,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 4: Shift close with counted cash (match and shortage)", () => {
  let item;
  let testCashier;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      items: [item],
      users: [testCashier?.user],
    });
  });

  test("shift close requires permission, records cash difference on shortage and match", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Open a new shift with opening balance 500.000
    const shift = await ensureShiftOpen(request, 1, "500.000");
    expect(shift).toBeTruthy();

    // 2. Perform a cash sale to add 100.000 to the drawer
    item = await createTestItem(request, {
      name: "صنف تجربة إغلاق وردية",
      price: "100.000",
      stock: "10.000",
    });

    const saleRes = await apiCall(request, "POST", "/api/v1/invoices", {
      storeId: 1,
      data: {
        store_id: 1,
        payment_type: "cash",
        payment_method: "cash",
        paid_amount: "100.000",
        items: [{ item_id: item.id, quantity: "1.000", unit_price: "100.000" }],
      },
    });
    expect(saleRes.status).toBe(201);

    // 3. Permission check: Cashier without daily_journal.close_shift gets 403 unconditionally
    testCashier = await createTestUser(request, {
      role: "storekeeper", // storekeeper has no daily_journal.close_shift
      default_store_id: 1,
    });

    const unauthorizedClose = await apiCall(
      request,
      "POST",
      "/api/v1/shifts/close",
      {
        storeId: 1,
        token: testCashier.token,
        data: {
          shift_id: shift.id,
          actual_cash_balance: "600.000",
          notes: "محاولة إغلاق غير مصرح بها E2E",
        },
      },
    );
    expect(unauthorizedClose.status).toBe(403);

    // 4. Authorized Close with Shortage: Actual 550.000 vs Expected 600.000 (Shortage -50.000)
    const shortageCloseRes = await apiCall(
      request,
      "POST",
      "/api/v1/shifts/close",
      {
        storeId: 1,
        data: {
          shift_id: shift.id,
          actual_cash_balance: "550.000",
          notes: "إغلاق مع عجز مقداره 50 جنيه E2E",
        },
      },
    );

    expect(shortageCloseRes.status).toBe(200);
    expect(shortageCloseRes.body.success).toBe(true);

    const closedData = shortageCloseRes.body.data;
    expect(closedData.status).toBe("closed");
    expect(toDecimal3(closedData.actual_cash_balance)).toBe("550.000");
    // Verify cash difference is negative (shortage) without relying on translated strings
    const shortageDiff = parseFloat(closedData.cash_difference);
    expect(shortageDiff).toBeLessThan(0);

    // 5. Test Match: Open new shift and close with exact match
    const shift2 = await ensureShiftOpen(request, 1, "300.000");
    expect(shift2).toBeTruthy();

    const matchCloseRes = await apiCall(
      request,
      "POST",
      "/api/v1/shifts/close",
      {
        storeId: 1,
        data: {
          shift_id: shift2.id,
          actual_cash_balance: "300.000",
          notes: "إغلاق مع مطابقة تامة E2E",
        },
      },
    );

    expect(matchCloseRes.status).toBe(200);
    expect(toDecimal3(matchCloseRes.body.data.cash_difference)).toBe("0.000");

    // 6. UI Verification: Visit daily journal view
    await page.goto("/daily-journal", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
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
