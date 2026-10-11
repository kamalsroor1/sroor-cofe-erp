import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  toDecimal3,
  decAdd,
  decSub,
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
    expect(shiftBefore.status).toBe(200);

    const expensesBefore = toDecimal3(
      shiftBefore.body?.metrics?.total_expenses,
    );
    const expectedCashBefore = toDecimal3(
      shiftBefore.body?.metrics?.expected_cash_balance,
    );

    // 2. Record Operational Expense (85.500 EGP Cash)
    const today = new Date().toISOString().split("T")[0];
    const expenseRes = await apiCall(request, "POST", "/api/v1/expenses", {
      storeId: 1,
      data: {
        title: "فاتورة صيانة وضيافة دورية E2E",
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
    expect(toDecimal3(expense.amount)).toBe("85.500");

    // 3. Server State Verification: Shift expenses increased and expected treasury cash decreased
    const shiftAfter = await apiCall(request, "GET", "/api/v1/shifts/current", {
      storeId: 1,
    });
    const expensesAfter = toDecimal3(shiftAfter.body?.metrics?.total_expenses);
    const expectedCashAfter = toDecimal3(
      shiftAfter.body?.metrics?.expected_cash_balance,
    );

    expect(expensesAfter).toBe(decAdd(expensesBefore, "85.500"));
    expect(expectedCashAfter).toBe(decSub(expectedCashBefore, "85.500"));

    // 4. Server State Verification: Verified expense row appears in Daily Journal via query param
    const journalRes = await apiCall(
      request,
      "GET",
      `/api/v1/daily-journal?date=${today}`,
      { storeId: 1 },
    );
    expect(journalRes.status).toBe(200);

    const journalExpenses = journalRes.body.data?.expenses || [];
    const matchedExpense = journalExpenses.find(
      (e) => Number(e.id) === Number(expense.id) || e.title === expense.title,
    );
    expect(matchedExpense).toBeDefined();
    expect(toDecimal3(matchedExpense?.amount)).toBe("85.500");

    // 5. UI Verification: Expenses page ledger
    await page.goto("/expenses", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator('[data-testid="expenses-table"]')).toBeVisible({
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
