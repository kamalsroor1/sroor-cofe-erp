import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestCustomer,
  createTestItem,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 2: POS credit sale to customer and settlement", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("credit sale -> customer balance increases -> statement payment -> balance settled", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create a dedicated test customer
    const customer = await createTestCustomer(request, {
      name: "عميل آجل بيزنس فلو",
      price_tier: "retail",
    });
    expect(customer.id).toBeDefined();

    const initialBalanceRes = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}`,
    );
    const initialBalance = parseFloat(
      initialBalanceRes.body?.data?.current_balance || "0",
    );

    // 2. Create a test item
    const item = await createTestItem(request, {
      name: "شاي أخضر فاخر",
      cost: "60.000",
      price: "250.000",
      stock: "15.000",
    });

    // 3. Perform POS Credit Sale (net: 250.000, paid: 0.000)
    const creditInvoiceRes = await apiCall(
      request,
      "POST",
      "/api/v1/invoices",
      {
        storeId: 1,
        data: {
          customer_id: customer.id,
          store_id: 1,
          payment_type: "credit",
          payment_method: "cash",
          paid_amount: "0.000",
          items: [
            {
              item_id: item.id,
              quantity: 1.0,
              unit_price: 250.0,
            },
          ],
        },
      },
    );

    expect(creditInvoiceRes.status).toBe(201);
    expect(creditInvoiceRes.body.success).toBe(true);
    const invoice = creditInvoiceRes.body.data;
    expect(parseFloat(invoice.net_total || invoice.net_amount)).toBeCloseTo(
      250.0,
      3,
    );

    // 4. Server State Verification: Customer balance increased by 250.000
    const customerAfterCredit = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}`,
    );
    const balanceAfterCredit = parseFloat(
      customerAfterCredit.body.data.current_balance,
    );
    expect(balanceAfterCredit).toBeCloseTo(initialBalance + 250.0, 3);

    const statementBeforePayment = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}/statement`,
    );
    expect(statementBeforePayment.status).toBe(200);
    const statementBalanceBefore = parseFloat(
      statementBeforePayment.body.data?.customer?.current_balance ??
        statementBeforePayment.body.data?.summary?.current_balance ??
        statementBeforePayment.body.data?.current_balance,
    );
    expect(statementBalanceBefore).toBeCloseTo(initialBalance + 250.0, 3);

    // 5. Pay the debt: Record customer payment voucher
    const paymentRes = await apiCall(
      request,
      "POST",
      "/api/v1/payments/customer-receipt",
      {
        data: {
          customer_id: customer.id,
          amount: "250.000",
          payment_method: "cash",
          invoice_id: invoice.id,
          notes: "سداد كامل الحساب المستحق E2E",
        },
      },
    );

    expect(paymentRes.status).toBe(201);
    expect(paymentRes.body.success).toBe(true);

    // 6. Server State Verification: Customer balance settled back
    const customerAfterPayment = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}`,
    );
    const balanceAfterPayment = parseFloat(
      customerAfterPayment.body.data.current_balance,
    );
    expect(balanceAfterPayment).toBeCloseTo(initialBalance, 3);

    const statementAfterPayment = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}/statement`,
    );
    const statementBalanceAfter = parseFloat(
      statementAfterPayment.body.data?.customer?.current_balance ??
        statementAfterPayment.body.data?.summary?.current_balance ??
        statementAfterPayment.body.data?.current_balance,
    );
    expect(statementBalanceAfter).toBeCloseTo(initialBalance, 3);

    // 7. UI Verification: Customer Statement View
    await page.goto(`/customers/${customer.id}/statement`, {
      waitUntil: "networkidle",
    });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText(customer.name);

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
