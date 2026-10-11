import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestCustomer,
  createTestItem,
  toDecimal3,
  collectConsoleErrors,
  cleanupTestRecords,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 6: Sales return full line, refund match and stock restoration", () => {
  let customer;
  let item;

  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test.afterAll(async ({ request }) => {
    await cleanupTestRecords(request, {
      customers: [customer],
      items: [item],
    });
  });

  test("sales return full line -> refund equals paid line total exactly, stock restored", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create test customer
    customer = await createTestCustomer(request, {
      name: "عميل مرتجعات مبيعات E2E",
    });
    expect(customer.id).toBeDefined();

    // 2. Create test item with initial stock of 20
    item = await createTestItem(request, {
      name: "بن يمني إسماعيلي مرتجع",
      cost: "80.000",
      price: "150.000",
      stock: "20.000",
    });

    const initialStock = toDecimal3(item.current_stock);

    // 3. Perform Sale: Sell 2.000 units (Total: 300.000)
    const saleRes = await apiCall(request, "POST", "/api/v1/invoices", {
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
            quantity: "2.000",
            unit_price: "150.000",
          },
        ],
      },
    });

    expect(saleRes.status).toBe(201);
    const invoice = saleRes.body.data;
    expect(toDecimal3(invoice.net_total || invoice.net_amount)).toBe("300.000");

    // Verify stock decreased to 18.000
    const itemAfterSaleRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const stockAfterSale = toDecimal3(itemAfterSaleRes.body.data.current_stock);
    expect(stockAfterSale).toBe("18.000");

    // Verify customer balance increased by 300.000
    const custAfterSale = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}`,
    );
    expect(toDecimal3(custAfterSale.body.data.current_balance)).toBe("300.000");

    // 4. Perform Full Line Sales Return linked to invoice_id: Return all 2 units @ 150.000 = 300.000 refund
    const today = new Date().toISOString().split("T")[0];
    const returnRes = await apiCall(request, "POST", "/api/v1/returns", {
      storeId: 1,
      data: {
        return_type: "sales_return",
        invoice_id: invoice.id,
        customer_id: customer.id,
        return_date: today,
        refund_amount: "300.000",
        reason: "إرجاع سليم بالكامل E2E",
        items: [
          {
            item_id: item.id,
            quantity: "2.000",
            unit_price: "150.000",
          },
        ],
      },
    });

    expect(returnRes.status).toBe(201);
    expect(returnRes.body.success).toBe(true);

    const returnDoc = returnRes.body.data;
    expect(toDecimal3(returnDoc.total_amount)).toBe("300.000");

    // 5. Server State Verification: Stock restored back to initial 20.000
    const itemAfterReturnRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const stockAfterReturn = toDecimal3(
      itemAfterReturnRes.body.data.current_stock,
    );
    expect(stockAfterReturn).toBe(initialStock);

    // Verify customer debt is settled/reduced back after return
    const custAfterReturn = await apiCall(
      request,
      "GET",
      `/api/v1/customers/${customer.id}`,
    );
    expect(toDecimal3(custAfterReturn.body.data.current_balance)).toBe("0.000");

    // 6. UI Verification: Returns ledger view
    await page.goto("/returns", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator('[data-testid="returns-table"]')).toBeVisible({
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
