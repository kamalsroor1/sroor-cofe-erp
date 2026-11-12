// PERF-01: the customer statement is paged by the API. Everything is mocked, so the cases do not depend
// on what the local database holds: "all" sends period=all, a dated period starts with an opening-balance
// row, the pager loads the next page with a continuing running balance, print collects every page, and a
// 422 is explained next to the date filter.
import { test, expect } from "@playwright/test";
import { t } from "../utils/translations.js";

const TOTAL = 250;
const OPENING = 1250;
const MONEY = (n) => n.toFixed(3);
// The SPA may render Western or Arabic-Indic digits and any grouping separator.
const digits = (value) =>
  new RegExp(
    String(value)
      .split("")
      .map((c) => `[${c}${"٠١٢٣٤٥٦٧٨٩"[Number(c)] ?? ""}]`)
      .join("[,٬.٫]?"),
  );

const statementBody = ({ query, requests }) => {
  const from = query.get("from_date");
  const to = query.get("to_date");
  const all = query.get("period") === "all";
  const page = Number(query.get("page") || 1);
  const perPage = Number(query.get("per_page") || 100);
  requests.push({ from, to, period: query.get("period"), page, perPage });

  const opening = all ? 0 : OPENING;
  const start = (page - 1) * perPage;
  const count = Math.max(0, Math.min(perPage, TOTAL - start));
  const ledger = Array.from({ length: count }, (_, i) => {
    const n = start + i + 1;
    return {
      date: "2026-10-01",
      type: "invoice",
      ref_number: `INV-${n}`,
      debit: MONEY(1),
      credit: MONEY(0),
      balance_after: MONEY(opening + n),
      notes: null,
    };
  });
  return {
    success: true,
    data: {
      customer: {
        id: 7,
        name: "Paged Customer",
        phone: "01000000000",
        current_balance: MONEY(opening + TOTAL),
      },
      filters: {
        from_date: all ? null : from,
        to_date: all ? null : to,
        all_history: all,
      },
      summary: {
        total_debit: MONEY(TOTAL),
        total_credit: MONEY(0),
        current_balance: MONEY(opening + TOTAL),
        transactions_count: TOTAL,
        opening_balance: MONEY(opening),
        closing_balance: MONEY(opening + TOTAL),
      },
      ledger,
      meta: {
        current_page: page,
        per_page: perPage,
        total: TOTAL,
        last_page: Math.ceil(TOTAL / perPage),
      },
    },
  };
};

const mockStatement = async (
  page,
  { delayPrintMs = 0, failWith422 = null } = {},
) => {
  const requests = [];
  await page.route("**/api/v1/customers/7/statement*", async (route) => {
    const query = new URL(route.request().url()).searchParams;
    if (failWith422 && query.get("from_date")) {
      return route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          success: false,
          message: failWith422,
          errors: { to_date: [failWith422] },
        }),
      });
    }
    if (delayPrintMs && query.get("per_page") === "200") {
      await new Promise((resolve) => setTimeout(resolve, delayPrintMs));
    }
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(statementBody({ query, requests })),
    });
  });
  return requests;
};

const visibleTestId = (page, id) =>
  page.locator(`[data-testid="${id}"]:visible`);

test.describe("Customer statement: paged by the API", () => {
  test('the "all" preset sends period=all and shows no opening row', async ({
    page,
  }) => {
    const requests = await mockStatement(page);
    await page.goto("/customers/7/statement");

    await expect(visibleTestId(page, "customer-statement-table")).toBeVisible();
    await expect.poll(() => requests.length).toBeGreaterThan(0);
    expect(requests[0]).toMatchObject({
      period: "all",
      from: null,
      to: null,
      page: 1,
      perPage: 100,
    });
    await expect(page.getByTestId("statement-opening-row")).toHaveCount(0);
  });

  test("a dated period starts with the opening-balance row and the API closing balance", async ({
    page,
  }) => {
    const requests = await mockStatement(page);
    await page.goto("/customers/7/statement");
    await page.getByRole("button", { name: t("common.this_month") }).click();

    await expect(visibleTestId(page, "statement-opening-row")).toBeVisible();
    await expect(visibleTestId(page, "statement-opening-row")).toHaveText(
      t("contacts.statement_opening_row"),
    );
    const last = requests.at(-1);
    expect(last.period).toBeNull();
    expect(last.from).not.toBeNull();
    await expect(page.getByTestId("statement-opening-balance")).toContainText(
      digits(1250),
    );
    await expect(page.getByTestId("statement-closing-balance")).toContainText(
      digits(1500),
    );
  });

  test("the pager loads page 2 and the running balance continues", async ({
    page,
  }) => {
    const requests = await mockStatement(page);
    await page.goto("/customers/7/statement");
    await page.getByRole("button", { name: t("common.this_month") }).click();
    await expect(visibleTestId(page, "statement-opening-row")).toBeVisible();

    await visibleTestId(page, "pagination-next").first().click();
    await expect.poll(() => requests.at(-1).page).toBe(2);
    // First row of page 2 is entry 101: balance 1250 + 101.
    await expect(
      page.getByText(digits(1351)).locator("visible=true").first(),
    ).toBeVisible();
    await expect(visibleTestId(page, "statement-opening-row")).toHaveCount(0);
  });

  test("print fetches every page (200 rows each) with a visible progress state", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      window.__printCalls = 0;
      window.print = () => {
        window.__printCalls += 1;
        const sheet = document.querySelector(
          '[data-testid="statement-print-sheet"]',
        );
        window.__printRows = sheet
          ? sheet.querySelectorAll("tbody tr").length
          : -1;
        window.__printClosing =
          sheet?.querySelector('[data-testid="statement-print-closing"]')
            ?.textContent ?? "";
      };
    });
    const requests = await mockStatement(page, { delayPrintMs: 400 });
    await page.goto("/customers/7/statement");
    await page.getByRole("button", { name: t("common.this_month") }).click();
    await expect(visibleTestId(page, "statement-opening-row")).toBeVisible();

    await page.getByTestId("statement-print-button").click();
    await expect(page.getByTestId("statement-print-progress")).toBeVisible();
    await expect.poll(() => page.evaluate(() => window.__printCalls)).toBe(1);

    const printRequests = requests.filter((r) => r.perPage === 200);
    expect(printRequests.map((r) => r.page)).toEqual([1, 2]);
    // opening row + all 250 entries, not the 100 of the visible page
    expect(await page.evaluate(() => window.__printRows)).toBe(TOTAL + 1);
    expect(await page.evaluate(() => window.__printClosing)).toMatch(
      digits(1500),
    );
    await expect(page.getByTestId("statement-print-sheet")).toBeHidden();
  });

  test("a 422 is shown next to the date filter and the previous rows stay", async ({
    page,
  }) => {
    const message = "range rejected by the server";
    await mockStatement(page, { failWith422: message });
    await page.goto("/customers/7/statement");
    await expect(visibleTestId(page, "customer-statement-table")).toBeVisible();

    await page.getByRole("button", { name: t("common.this_month") }).click();
    await expect(page.getByTestId("statement-range-error")).toHaveText(message);
    await expect(visibleTestId(page, "customer-statement-table")).toBeVisible();
    await expect(page.getByTestId("error-state")).toHaveCount(0);
  });
});
