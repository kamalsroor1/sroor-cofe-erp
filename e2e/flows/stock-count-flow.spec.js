// SHOP-02: stocktake counting flow.
// Covers:
//  - opening an open stocktake session
//  - counting 3 items by scanning their barcodes (each scan adds 1, a second scan of the same item adds 1 more)
//  - saving the draft (PUT /lines carries exactly the scanned quantities)
//  - posting with the confirm modal (POST /post) and the read-only state afterwards
//
// The session endpoints are mocked via page.route, so nothing is written to the database and the
// run is deterministic. The e2e user is the tenant admin (holds stock.count). Selectors use
// data-testid, never translated text.
import { test, expect } from "@playwright/test";

const line = (id, code, name, system) => ({
  id,
  item_id: id,
  item_name: name,
  item_code: code,
  unit: "قطعة",
  system_qty: system,
  system_qty_at_post: null,
  counted_qty: null,
  difference: null,
  unit_cost: "10.000",
  difference_value: null,
  counted_at: null,
});

const baseCount = {
  id: 501,
  count_number: "CNT-20261119-0001",
  status: "draft",
  status_label: "draft",
  is_open: true,
  is_blind: false,
  store_id: 1,
  store_name: "Main",
  category_id: null,
  category_name: null,
  notes: null,
  lines_count: 4,
  counted_lines_count: 0,
  can_view_cost: true,
  created_at: "2026-11-19T10:00:00+02:00",
  posted_at: null,
  cancelled_at: null,
  lines: [
    line(11, "E2E-CNT-A", "Item A", "5.000"),
    line(12, "E2E-CNT-B", "Item B", "2.000"),
    line(13, "E2E-CNT-C", "Item C", "0.000"),
    line(14, "E2E-CNT-D", "Item D", "9.000"),
  ],
};

const withCounts = (counts, extra = {}) => ({
  ...baseCount,
  ...extra,
  counted_lines_count: Object.keys(counts).length,
  lines: baseCount.lines.map((l) =>
    l.item_id in counts ? { ...l, counted_qty: counts[l.item_id] } : l,
  ),
});

test.describe("SHOP-02: stocktake counting flow", () => {
  test("count 3 items by barcode, save and post", async ({ page }) => {
    let savedPayload = null;
    let postPayload = null;

    await page.route("**/api/v1/stock-counts/501", (route) =>
      route.request().method() === "GET"
        ? route.fulfill({
            status: 200,
            json: { success: true, data: baseCount },
          })
        : route.fallback(),
    );
    await page.route("**/api/v1/stock-counts/501/lines", (route) => {
      if (route.request().method() !== "PUT") return route.fallback();
      savedPayload = route.request().postDataJSON();
      const counts = Object.fromEntries(
        savedPayload.lines.map((l) => [l.item_id, l.counted_qty]),
      );
      return route.fulfill({
        status: 200,
        json: {
          success: true,
          message: "saved",
          data: withCounts(counts, { status: "counting" }),
        },
      });
    });
    await page.route("**/api/v1/stock-counts/501/post", (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      postPayload = route.request().postDataJSON();
      const counts = { 11: "2.000", 12: "1.000", 13: "1.000" };
      return route.fulfill({
        status: 200,
        json: {
          success: true,
          message: "posted",
          data: withCounts(counts, {
            status: "posted",
            status_label: "posted",
            is_open: false,
          }),
        },
      });
    });

    await page.goto("/stock-counts/501", { waitUntil: "networkidle" });
    await expect(page.getByTestId("stock-count-header")).toBeVisible();

    const scan = page.getByTestId("stock-count-scan");
    for (const code of ["E2E-CNT-A", "E2E-CNT-B", "E2E-CNT-C", "E2E-CNT-A"]) {
      await scan.fill(code);
      await scan.press("Enter");
    }

    await expect(
      page
        .getByTestId("stock-count-line-E2E-CNT-A")
        .getByTestId("stock-count-qty"),
    ).toHaveValue("2.000");
    await expect(
      page
        .getByTestId("stock-count-line-E2E-CNT-B")
        .getByTestId("stock-count-qty"),
    ).toHaveValue("1.000");
    await expect(
      page
        .getByTestId("stock-count-line-E2E-CNT-C")
        .getByTestId("stock-count-qty"),
    ).toHaveValue("1.000");
    await expect(
      page
        .getByTestId("stock-count-line-E2E-CNT-D")
        .getByTestId("stock-count-qty"),
    ).toHaveValue("");

    await page.getByTestId("stock-count-save").click();
    await expect.poll(() => savedPayload).not.toBeNull();
    expect(savedPayload.lines).toEqual(
      expect.arrayContaining([
        { item_id: 11, counted_qty: "2.000" },
        { item_id: 12, counted_qty: "1.000" },
        { item_id: 13, counted_qty: "1.000" },
      ]),
    );
    expect(savedPayload.lines).toHaveLength(3);

    await page.getByTestId("stock-count-post").click();
    await page.getByTestId("stock-count-post-confirm").click();
    await expect.poll(() => postPayload).toEqual({ zero_uncounted: false });

    await expect(page.getByTestId("stock-count-status")).toBeVisible();
    await expect(page.getByTestId("stock-count-scan")).toHaveCount(0);
    await expect(page.getByTestId("stock-count-post")).toHaveCount(0);
  });
});
