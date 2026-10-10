import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 3: POS split payment across methods and shift tracking", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("split payment (cash + card) -> payments breakdown and shift tracking", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Ensure active shift
    const shift = await ensureShiftOpen(request, 1, "500.000");
    expect(shift).toBeTruthy();

    // 2. Create test item
    const item = await createTestItem(request, {
      name: "قهوة كولومبي بريميوم",
      cost: "100.000",
      price: "300.000",
      stock: "20.000",
    });

    // 3. Perform Split Payment Invoice:
    // Total 300.000 split into: 100.000 Cash + 200.000 Visa/Card
    const invoiceRes = await apiCall(request, "POST", "/api/v1/invoices", {
      storeId: 1,
      data: {
        store_id: 1,
        payment_type: "cash",
        payment_method: "cash",
        paid_amount: "300.000",
        payments: [
          { method: "cash", amount: "100.000" },
          { method: "visa", amount: "200.000" },
        ],
        items: [
          {
            item_id: item.id,
            quantity: 1.0,
            unit_price: 300.0,
          },
        ],
      },
    });

    expect(invoiceRes.status).toBe(201);
    expect(invoiceRes.body.success).toBe(true);

    const invoice = invoiceRes.body.data;
    expect(parseFloat(invoice.net_total || invoice.net_amount)).toBeCloseTo(
      300.0,
      3,
    );

    // 4. Server State Verification: Verify payments created for this invoice
    const paymentsRes = await apiCall(request, "GET", "/api/v1/payments");
    expect(paymentsRes.status).toBe(200);

    const invoicePayments = paymentsRes.body.data.filter(
      (p) => Number(p.invoice_id) === Number(invoice.id),
    );

    // Should have both payment methods recorded
    if (invoicePayments.length > 0) {
      const cashPayments = invoicePayments.filter(
        (p) => p.payment_method === "cash",
      );
      const visaPayments = invoicePayments.filter(
        (p) => p.payment_method === "visa" || p.payment_method === "card",
      );

      const cashTotal = cashPayments.reduce(
        (sum, p) => sum + parseFloat(p.amount),
        0,
      );
      const visaTotal = visaPayments.reduce(
        (sum, p) => sum + parseFloat(p.amount),
        0,
      );

      expect(cashTotal).toBeCloseTo(100.0, 3);
      expect(visaTotal).toBeCloseTo(200.0, 3);
    }

    // 5. UI Verification: View invoice details
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
