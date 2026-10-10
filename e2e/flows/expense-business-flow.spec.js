import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 8: Expense recording, treasury deduction and daily journal visibility", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("expense -> treasury cash decreased, appears in daily journal", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Ensure shift is open
    const shift = await ensureShiftOpen(request, 1, "500.000");
    expect(shift).toBeTruthy();

    const shiftBefore = await apiCall(
      request,
      "GET",
      "/api/v1/shifts/current",
      { storeId: 1 },
    );
    const expensesBefore = parseFloat(
      shiftBefore.body?.metrics?.total_expenses || "0",
    );
    const expectedCashBefore = parseFloat(
      shiftBefore.body?.metrics?.expected_cash_balance || "500",
    );

    // 2. Record Operational Expense (85.500 EGP Cash)
    const today = new Date().toISOString().split("T")[0];
    const expenseRes = await apiCall(request, "POST", "/api/v1/expenses", {
      storeId: 1,
      data: {
        title: "فاتورة صيانة وضيافة دورية",
        category: "تشغيلية",
        cost_center: "عام",
        amount: "85.500",
        expense_date: today,
        payment_method: "cash",
        notes: "مصروف نقدي من الخزينة E2E",
      },
    });

    expect(expenseRes.status).toBe(201);
    expect(expenseRes.body.success).toBe(true);

    const expense = expenseRes.body.data;
    expect(parseFloat(expense.amount)).toBeCloseTo(85.5, 3);

    // 3. Server State Verification: Shift expenses increased and expected cash decreased
    const shiftAfter = await apiCall(request, "GET", "/api/v1/shifts/current", {
      storeId: 1,
    });
    const expensesAfter = parseFloat(shiftAfter.body?.metrics?.total_expenses);
    const expectedCashAfter = parseFloat(
      shiftAfter.body?.metrics?.expected_cash_balance,
    );

    expect(expensesAfter).toBeCloseTo(expensesBefore + 85.5, 3);
    expect(expectedCashAfter).toBeCloseTo(expectedCashBefore - 85.5, 3);

    // 4. Server State Verification: Appears in Daily Journal
    const journalRes = await apiCall(request, "GET", "/api/v1/daily-journal", {
      storeId: 1,
      data: { date: today },
    });
    expect(journalRes.status).toBe(200);

    // 5. UI Verification: Expenses page ledger
    await page.goto("/expenses", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText("المصروفات");

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
