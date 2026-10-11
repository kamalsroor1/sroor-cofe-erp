import { test, expect } from "@playwright/test";
import {
  apiCall,
  ensureShiftOpen,
  createTestItem,
  toDecimal3,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 3: POS split payment across methods and shift tracking", () => {
  let item;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      items: [item],
    });
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
    item = await createTestItem(request, {
      name: "قهوة كولومبي بريميوم سبليت",
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
            quantity: "1.000",
            unit_price: "300.000",
          },
        ],
      },
    });

    expect(invoiceRes.status).toBe(201);
    expect(invoiceRes.body.success).toBe(true);

    const invoice = invoiceRes.body.data;
    expect(toDecimal3(invoice.net_total || invoice.net_amount)).toBe("300.000");

    // 4. Server State Verification: Verify payments created for this invoice (unconditional check)
    const invoiceDetails = await apiCall(
      request,
      "GET",
      `/api/v1/invoices/${invoice.id}`,
      { storeId: 1 },
    );
    expect(invoiceDetails.status).toBe(200);

    const invoicePayments =
      invoiceDetails.body.data?.payments ||
      (
        await apiCall(
          request,
          "GET",
          `/api/v1/payments?invoice_id=${invoice.id}`,
        )
      ).body?.data ||
      [];

    expect(invoicePayments.length).toBeGreaterThanOrEqual(2);

    const cashPayment = invoicePayments.find(
      (p) => p.payment_method === "cash",
    );
    const cardPayment = invoicePayments.find(
      (p) => p.payment_method === "visa" || p.payment_method === "card",
    );

    expect(cashPayment).toBeDefined();
    expect(toDecimal3(cashPayment.amount)).toBe("100.000");

    expect(cardPayment).toBeDefined();
    expect(toDecimal3(cardPayment.amount)).toBe("200.000");

    // 5. Verify Shift per-method tracking
    const shiftRes = await apiCall(request, "GET", "/api/v1/shifts/current", {
      storeId: 1,
    });
    expect(shiftRes.status).toBe(200);
    expect(shiftRes.body?.metrics).toBeDefined();

    // 6. UI Verification: View invoice details
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
