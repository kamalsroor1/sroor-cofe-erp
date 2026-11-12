// PERF-01: the items / inventory / treasury report tabs load their own paged endpoints, badges come from
// meta.total (not the 100 rows /reports/comprehensive embeds), the treasury summary reads the real keys and
// a rejected period is explained next to the date filter. The API is mocked.
import { test, expect } from "@playwright/test";
import { t } from "../utils/translations.js";

const MONEY = (n) => Number(n).toFixed(3);
const digits = (value) =>
  new RegExp(
    String(value)
      .split("")
      .map((c) => `[${c}${"٠١٢٣٤٥٦٧٨٩"[Number(c)] ?? ""}]`)
      .join("[,٬.٫]?"),
  );

const ITEMS_TOTAL = 4321;
const INVENTORY_TOTAL = 4990;
const LEDGER_TOTAL = 40123;

const itemRow = (n) => ({
  item_id: n,
  name: `Item ${n}`,
  code: `C${n}`,
  category: "Cat",
  unit: "kg",
  total_qty: "2.000",
  total_revenue: MONEY(n),
  total_cogs: "1.000",
  profit: MONEY(n - 1),
  margin: "10.0",
  gross_qty: "2.000",
  gross_revenue: MONEY(n),
  gross_cogs: "1.000",
  returned_qty: "0.000",
  returned_value: "0.000",
  returned_cogs: "0.000",
});

const pageOf = (query, total, build) => {
  const page = Number(query.get("page") || 1);
  const perPage = Number(query.get("per_page") || 100);
  const start = (page - 1) * perPage;
  const count = Math.max(0, Math.min(perPage, total - start));
  return {
    page,
    rows: Array.from({ length: count }, (_, i) => build(start + i + 1)),
    meta: {
      current_page: page,
      per_page: perPage,
      total,
      last_page: Math.ceil(total / perPage),
    },
  };
};

const json = (route, body, status = 200) =>
  route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });

const mockReports = async (page, { comprehensive422 = null } = {}) => {
  const calls = { items: [], inventory: [], treasury: [], comprehensive: [] };

  await page.route("**/api/v1/reports/comprehensive*", (route) => {
    const query = new URL(route.request().url()).searchParams;
    calls.comprehensive.push(Object.fromEntries(query));
    if (comprehensive422 && query.get("period") === "this_year") {
      return json(
        route,
        {
          success: false,
          message: comprehensive422,
          errors: { to: [comprehensive422] },
        },
        422,
      );
    }
    return json(route, {
      success: true,
      summary: { total_sales: "500.000", invoices_count: 3 },
      item_profits: Array.from({ length: 100 }, (_, i) => itemRow(i + 1)),
      item_profits_totals: {},
      store_breakdown: [],
      customer_sales: [],
      expenses_breakdown: [],
      inventory_data: {
        stock_cost_valuation: "10.000",
        stock_selling_valuation: "20.000",
        expected_stock_profit: "10.000",
        items: [],
      },
      treasury_data: {
        total_inflows: "131936.000",
        total_outflows: "550.000",
        total_net_change: "131386.000",
        ledger_entries: [],
        ledger_total: LEDGER_TOTAL,
      },
      meta: {
        list_limit: 100,
        item_profits: { total: ITEMS_TOTAL, shown: 100 },
        inventory_items: { total: INVENTORY_TOTAL, shown: 100 },
        treasury_ledger: { total: LEDGER_TOTAL, shown: 100 },
      },
    });
  });

  await page.route("**/api/v1/reports/items*", (route) => {
    const query = new URL(route.request().url()).searchParams;
    calls.items.push(Object.fromEntries(query));
    const { rows, meta } = pageOf(query, ITEMS_TOTAL, itemRow);
    return json(route, {
      success: true,
      data: rows,
      totals: {
        total_qty: "8642.000",
        total_revenue: "987654.000",
        total_cogs: "1.000",
        profit: "987653.000",
        margin: "99.9",
        gross_revenue: "987654.000",
        returned_value: "0.000",
      },
      meta,
    });
  });

  await page.route("**/api/v1/reports/inventory*", (route) => {
    const query = new URL(route.request().url()).searchParams;
    calls.inventory.push(Object.fromEntries(query));
    const { rows, meta } = pageOf(query, INVENTORY_TOTAL, (n) => ({
      id: n,
      name: `Stock ${n}`,
      code: `S${n}`,
      category: "Cat",
      unit: "kg",
      current_stock: "3.000",
      cost_price: "1.000",
      selling_price: "2.000",
      cost_val: "3.000",
      sell_val: "6.000",
      profit: "3.000",
    }));
    return json(route, {
      success: true,
      data: {
        stock_cost_valuation: "10.000",
        stock_selling_valuation: "20.000",
        expected_stock_profit: "10.000",
        items: rows,
        meta,
      },
    });
  });

  await page.route("**/api/v1/reports/treasury*", (route) => {
    const query = new URL(route.request().url()).searchParams;
    calls.treasury.push(Object.fromEntries(query));
    const { rows, meta } = pageOf(query, LEDGER_TOTAL, (n) => ({
      date: "2026-11-01",
      time: "10:00",
      doc_number: `DOC-${n}`,
      type_label: "Receipt",
      method_label: "Cash",
      party: "Party",
      debit: "5.000",
      credit: "0.000",
      running_balance: MONEY(n),
    }));
    return json(route, {
      success: true,
      data: {
        total_inflows: "131936.000",
        total_outflows: "550.000",
        total_net_change: "131386.000",
        ledger_entries: rows,
        ledger_total: LEDGER_TOTAL,
        meta,
      },
    });
  });

  return calls;
};

const openTab = async (page, key) => {
  await page.goto("/reports");
  await page.getByRole("button", { name: t(`reports.tab_${key}`) }).click();
};

test.describe("Reports: paged tabs", () => {
  test("items tab shows the API total (not 100), the API totals row, and pages", async ({
    page,
  }) => {
    const calls = await mockReports(page);
    await openTab(page, "items");

    await expect(page.getByTestId("items-count-badge")).toContainText(
      digits(ITEMS_TOTAL),
    );
    await expect(page.getByTestId("items-count-badge")).not.toContainText(
      digits(100),
    );
    await expect(
      page
        .getByTestId("items-totals-row")
        .or(page.getByTestId("items-totals-card"))
        .locator("visible=true")
        .first(),
    ).toBeVisible();
    expect(calls.items[0]).toMatchObject({ page: "1", per_page: "25" });

    await page
      .locator(
        '[data-testid="items-pagination"] [data-testid="pagination-next"]:visible',
      )
      .first()
      .click();
    await expect.poll(() => calls.items.at(-1).page).toBe("2");
  });

  test("inventory tab badge uses meta.total and the first request is paged", async ({
    page,
  }) => {
    const calls = await mockReports(page);
    await openTab(page, "inventory");

    await expect(page.getByTestId("inventory-count-badge")).toContainText(
      digits(INVENTORY_TOTAL),
    );
    expect(calls.inventory[0]).toMatchObject({ page: "1", per_page: "25" });
  });

  test("treasury summary shows the real figures and the ledger is paged", async ({
    page,
  }) => {
    const calls = await mockReports(page);
    await openTab(page, "treasury");

    await expect(page.getByText(digits(131936)).first()).toBeVisible();
    await expect(page.getByText(digits(550)).first()).toBeVisible();
    await expect(page.getByText(digits(131386)).first()).toBeVisible();
    await expect(page.getByTestId("treasury-ledger-badge")).toContainText(
      digits(LEDGER_TOTAL),
    );
    expect(calls.treasury[0]).toMatchObject({ page: "1", per_page: "25" });
  });

  test("a rejected period is explained by the date filter and the data stays", async ({
    page,
  }) => {
    const message = "period too long from the server";
    await mockReports(page, { comprehensive422: message });
    await page.goto("/reports");
    await expect(page.getByTestId("error-state")).toHaveCount(0);

    await page.getByRole("button", { name: t("common.this_year") }).click();
    await expect(page.getByTestId("report-period-error")).toHaveText(message);
    await expect(page.getByTestId("error-state")).toHaveCount(0);
  });
});
