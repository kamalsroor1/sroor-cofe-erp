// UI-05: the home screen leads with the numbers, then a quick-actions bar; everything is mocked so the
// cases (new shop, failure, cashier, setup progress) do not depend on what the local database holds.
import { test, expect } from "@playwright/test";
import { t } from "../utils/translations.js";

const metrics = (overrides = {}) => ({
  today_sales: "1250.500",
  today_gross_sales: "1250.500",
  today_returns: "0.000",
  today_returns_count: 0,
  today_invoices_count: 7,
  cash_sales: "900.000",
  credit_sales: "350.500",
  monthly_sales: "18400.000",
  monthly_returns: "0.000",
  monthly_gross_profit: "5200.250",
  monthly_margin: "28.26",
  customers_debt: "2300.000",
  customers_count: 12,
  ...overrides,
});

const dashboard = ({
  metricOverrides = {},
  invoices,
  periodSales = "18400.000",
} = {}) => ({
  success: true,
  data: {
    metrics: metrics(metricOverrides),
    can_view_financials: true,
    analytics: {
      daily_trend: [
        {
          date: "2026-11-12",
          label: "x",
          sales: "1250.500",
          sales_formatted: "1,250.500",
          invoices: 7,
        },
      ],
      hourly_sales: [],
      peak_hour: null,
      payment_distribution: [],
      period: { sales: periodSales, basket_size: "2.000" },
    },
    recent_invoices: invoices ?? [
      {
        id: 1,
        invoice_number: "INV-1",
        customer_name: "A",
        invoice_date: "2026-11-12",
        net_total: "10.000",
        paid_amount: "10.000",
        remaining_amount: "0.000",
        payment_type: "cash",
        status: "approved",
      },
    ],
    low_stock_items: [],
    active_shift: null,
  },
});

const SETUP_PARTIAL = {
  company_info: false,
  branch_setup: true,
  categories: true,
  items: true,
  users: true,
  shift: false,
  first_sale: false,
};
const SETUP_DONE = Object.fromEntries(
  Object.keys(SETUP_PARTIAL).map((key) => [key, true]),
);

async function mockApp(
  page,
  {
    dashboardBody,
    dashboardStatus = 200,
    permissions = null,
    setup = SETUP_PARTIAL,
  } = {},
) {
  await page.route("**/api/v1/dashboard", (route) =>
    route.fulfill({
      status: dashboardStatus,
      contentType: "application/json",
      body: JSON.stringify(
        dashboardStatus === 200
          ? dashboardBody
          : { success: false, message: "boom" },
      ),
    }),
  );
  await page.route("**/api/v1/system/context*", async (route) => {
    const response = await route.fetch();
    const json = await response.json();
    json.data.setup_progress = setup;
    await route.fulfill({ response, json });
  });
  await page.route("**/api/v1/system/setup-progress", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ success: true, data: setup }),
    }),
  );
  if (permissions) {
    await page.route("**/api/v1/auth/me", async (route) => {
      const response = await route.fetch();
      const json = await response.json();
      json.data.user.roles = ["cashier"];
      json.data.user.permissions = permissions;
      await route.fulfill({ response, json });
    });
    // The cached user decides the first render, before /auth/me answers.
    await page.addInitScript((granted) => {
      const raw = localStorage.getItem("auth_user");
      if (!raw) return;
      const user = JSON.parse(raw);
      localStorage.setItem(
        "auth_user",
        JSON.stringify({ ...user, roles: ["cashier"], permissions: granted }),
      );
    }, permissions);
  }
}

const open = async (page, url = "/dashboard") => {
  await page.goto(url);
  await expect(page.getByTestId("dashboard-header")).toBeVisible();
};

test.describe("Dashboard: numbers first", () => {
  for (const viewport of [
    { name: "desktop 1366x768", width: 1366, height: 768 },
    { name: "phone 375x812", width: 375, height: 812 },
  ]) {
    test(`sales card sits in the first screen, POS button does not wrap (${viewport.name})`, async ({
      page,
    }) => {
      await page.setViewportSize({
        width: viewport.width,
        height: viewport.height,
      });
      await mockApp(page, { dashboardBody: dashboard() });
      await open(page);

      const sales = page.getByTestId("kpi-today-sales");
      await expect(sales).toBeVisible();
      await expect(sales).toBeInViewport({ ratio: 1 });
      await expect(page.getByTestId("kpi-today-invoices")).toBeInViewport({
        ratio: 1,
      });
      await expect(page.getByTestId("kpi-monthly-profit")).toBeVisible();
      await expect(page.getByTestId("kpi-customers-debt")).toBeVisible();

      const pos = page.getByTestId("dashboard-pos-btn");
      const box = await pos.boundingBox();
      expect(box.height).toBeGreaterThanOrEqual(44);
      expect(box.height).toBeLessThanOrEqual(48);
      await expect(page.getByTestId("dashboard-quick-actions")).toBeVisible();
    });
  }

  test("phone shows the four cards as a 2x2 grid", async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await mockApp(page, { dashboardBody: dashboard() });
    await open(page);

    const boxes = await Promise.all(
      [
        "kpi-today-sales",
        "kpi-today-invoices",
        "kpi-monthly-profit",
        "kpi-customers-debt",
      ].map((id) => page.getByTestId(id).boundingBox()),
    );
    expect(Math.abs(boxes[0].y - boxes[1].y)).toBeLessThan(2);
    expect(Math.abs(boxes[2].y - boxes[3].y)).toBeLessThan(2);
    expect(boxes[2].y).toBeGreaterThan(boxes[0].y + boxes[0].height - 2);
  });

  test("a shop without any sale shows zeros and an invitation, not an empty chart", async ({
    page,
  }) => {
    await mockApp(page, {
      dashboardBody: dashboard({
        invoices: [],
        periodSales: "0.000",
        metricOverrides: {
          today_sales: "0.000",
          today_invoices_count: 0,
          cash_sales: "0.000",
          credit_sales: "0.000",
          monthly_sales: "0.000",
          monthly_gross_profit: "0.000",
          monthly_margin: "0.00",
          customers_debt: "0.000",
        },
      }),
    });
    await open(page);

    await expect(page.getByTestId("kpi-today-sales")).toBeVisible();
    await expect(page.getByTestId("dashboard-first-sale")).toBeVisible();
    await expect(page.getByTestId("first-sale-cta")).toHaveAttribute(
      "href",
      "/pos",
    );
    await expect(page.getByText(t("dashboard.weekly_sales_trend"))).toHaveCount(
      0,
    );
  });

  test("a failed numbers request shows the error state with retry, never zeros", async ({
    page,
  }) => {
    await mockApp(page, { dashboardStatus: 500 });
    await open(page);

    await expect(page.getByTestId("error-state")).toBeVisible();
    await expect(page.getByTestId("retry-button")).toBeVisible();
    await expect(page.getByTestId("kpi-today-sales")).toHaveCount(0);
    await expect(page.getByTestId("dashboard-first-sale")).toHaveCount(0);
  });

  test("a cashier sees no profit or debt card and only the quick actions they may open", async ({
    page,
  }) => {
    await mockApp(page, {
      permissions: ["pos.access", "invoices.view"],
      dashboardBody: dashboard({
        metricOverrides: {
          monthly_gross_profit: null,
          monthly_margin: null,
          customers_debt: null,
        },
      }),
    });
    await open(page);

    await expect(page.getByTestId("kpi-today-sales")).toBeVisible();
    await expect(page.getByTestId("kpi-monthly-profit")).toHaveCount(0);
    await expect(page.getByTestId("kpi-customers-debt")).toHaveCount(0);

    await expect(page.getByTestId("quick-action-sale")).toBeVisible();
    for (const key of ["purchase", "item", "return", "collect"]) {
      await expect(page.getByTestId(`quick-action-${key}`)).toHaveCount(0);
    }
  });

  test("an admin gets every quick action, each at least 44px tall", async ({
    page,
  }) => {
    await mockApp(page, { dashboardBody: dashboard() });
    await open(page);

    for (const key of [
      "sale",
      "purchase",
      "item",
      "return",
      "collect",
      "all-screens",
    ]) {
      const action = page.getByTestId(`quick-action-${key}`);
      await expect(action).toBeVisible();
      expect((await action.boundingBox()).height).toBeGreaterThanOrEqual(44);
    }
  });

  test("setup checklist is one collapsed line that opens on tap and disappears when finished", async ({
    page,
  }) => {
    await mockApp(page, { dashboardBody: dashboard() });
    await open(page);

    const toggle = page.getByTestId("setup-checklist-toggle");
    await expect(toggle).toHaveAttribute("aria-expanded", "false");
    await expect(page.getByTestId("setup-checklist-steps")).toHaveCount(0);
    expect((await toggle.boundingBox()).height).toBeLessThanOrEqual(64);

    await toggle.click();
    await expect(toggle).toHaveAttribute("aria-expanded", "true");
    await expect(page.getByTestId("setup-checklist-steps")).toBeVisible();
  });

  test("no checklist once the setup is complete", async ({ page }) => {
    await mockApp(page, { dashboardBody: dashboard(), setup: SETUP_DONE });
    await open(page);

    await expect(page.getByTestId("kpi-today-sales")).toBeVisible();
    await expect(page.getByTestId("setup-checklist")).toHaveCount(0);
  });

  test("the all-screens page is reachable from the dashboard and goes back", async ({
    page,
  }) => {
    await mockApp(page, { dashboardBody: dashboard() });
    await open(page);

    await page.getByTestId("quick-action-all-screens").click();
    await expect(page).toHaveURL(/\/all-screens$/);
    await expect(
      page.getByRole("heading", { name: t("dashboard.menu_hub_title") }),
    ).toBeVisible();
    await expect(
      page.locator('main a[href="/invoices"], a[href="/invoices"]').first(),
    ).toBeVisible();

    await page.getByTestId("all-screens-back").click();
    await expect(page.getByTestId("dashboard-header")).toBeVisible();
  });
});
