import { test, expect } from "@playwright/test";
import {
  apiCall,
  createTestCustomer,
  createTestItem,
  collectConsoleErrors,
} from "../utils/flows/flow-helpers.js";

test.describe("Business Flow 6: Sales return full line, refund match and stock restoration", () => {
  test.beforeEach(async ({}, testInfo) => {
    test.setTimeout(90000);
    test.skip(testInfo.project.name !== "desktop", "Desktop project flow");
  });

  test("sales return full line -> refund equals paid line total exactly, stock restored", async ({
    page,
    request,
  }) => {
    const consoleErrors = collectConsoleErrors(page);

    // 1. Create test customer
    const customer = await createTestCustomer(request, {
      name: "عميل مرتجعات مبيعات",
    });
    expect(customer.id).toBeDefined();

    // 2. Create test item with initial stock of 20
    const item = await createTestItem(request, {
      name: "بن يمني إسماعيلي",
      cost: "80.000",
      price: "150.000",
      stock: "20.000",
    });

    const initialStock = parseFloat(item.current_stock || "20.000");

    // 3. Perform Sale: Sell 2.000 units (Total: 300.000)
    const saleRes = await apiCall(request, "POST", "/api/v1/invoices", {
      storeId: 1,
      data: {
        customer_id: customer.id,
        store_id: 1,
        payment_type: "cash",
        payment_method: "cash",
        paid_amount: "300.000",
        items: [
          {
            item_id: item.id,
            quantity: 2.0,
            unit_price: 150.0,
          },
        ],
      },
    });

    expect(saleRes.status).toBe(201);
    const invoice = saleRes.body.data;
    expect(parseFloat(invoice.net_total || invoice.net_amount)).toBeCloseTo(
      300.0,
      3,
    );

    // Verify stock decreased to 18
    const itemAfterSaleRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const stockAfterSale = parseFloat(itemAfterSaleRes.body.data.current_stock);
    expect(stockAfterSale).toBeCloseTo(initialStock - 2.0, 3);

    // 4. Perform Full Line Sales Return: Return all 2 units @ 150.000 = 300.000 refund
    const today = new Date().toISOString().split("T")[0];
    const returnRes = await apiCall(request, "POST", "/api/v1/returns", {
      storeId: 1,
      data: {
        return_type: "sales_return",
        customer_id: customer.id,
        return_date: today,
        refund_amount: "300.000",
        reason: "إرجاع سليم بالكامل E2E",
        items: [
          {
            item_id: item.id,
            quantity: 2.0,
            unit_price: 150.0,
          },
        ],
      },
    });

    expect(returnRes.status).toBe(201);
    expect(returnRes.body.success).toBe(true);

    const returnDoc = returnRes.body.data;
    expect(parseFloat(returnDoc.total_amount)).toBeCloseTo(300.0, 3);

    // 5. Server State Verification: Stock restored back to initial 20.000
    const itemAfterReturnRes = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId: 1 },
    );
    const stockAfterReturn = parseFloat(
      itemAfterReturnRes.body.data.current_stock,
    );
    expect(stockAfterReturn).toBeCloseTo(initialStock, 3);

    // 6. UI Verification: Returns ledger view
    await page.goto("/returns", { waitUntil: "networkidle" });
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({
      timeout: 15000,
    });
    await expect(page.locator("body")).toContainText("مرتجع");

    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes("favicon") &&
        !e.includes("Failed to load resource") &&
        !e.includes("net::ERR_"),
    );
    expect(criticalErrors).toHaveLength(0);
  });
});
