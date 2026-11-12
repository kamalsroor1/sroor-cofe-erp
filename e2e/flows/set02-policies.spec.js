// SET-02: store policies in the UI: the two new settings fields, the per-branch shift rule, and the
// sales-return window (notice before submit, manager-PIN dialog on `returns.window_expired`, resend).
//
// Real signed-in session and real GET endpoints where it does not matter; the writes and the
// out-of-window answers are mocked so the spec is deterministic and never changes the local data.
// Selectors are data-testids / ids / roles, never translated text.
import { test, expect } from "@playwright/test";

async function patchedJson(route, patch) {
  const response = await route.fetch();
  const body = await response.json();
  patch(body);
  return route.fulfill({ response, json: body });
}

async function openPosTab(page) {
  await page.goto("/settings", { waitUntil: "networkidle" });
  const mobileTab = page.getByTestId("mobile-tab-pos");
  if (await mobileTab.isVisible().catch(() => false)) {
    await mobileTab.click();
  } else {
    await page.getByTestId("tab-pos").click();
  }
  await expect(page.getByTestId("setting-returns-max-days")).toBeVisible();
}

test.describe("SET-02: settings fields", () => {
  test("save sends the return window and the idle minutes as integers; empty window = null", async ({
    page,
  }) => {
    const saved = [];
    await page.route("**/api/v1/settings", (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      saved.push(route.request().postDataJSON());
      return route.fulfill({ json: { success: true, message: "ok" } });
    });
    await openPosTab(page);

    await page.getByTestId("setting-returns-max-days").fill("30");
    await page.getByTestId("setting-pos-idle-lock-minutes").fill("5");
    await page.getByTestId("settings-pos-save").click();
    await expect.poll(() => saved.length).toBe(1);
    expect(saved[0].returns_max_days).toBe(30);
    expect(saved[0].pos_idle_lock_minutes).toBe(5);

    await page.getByTestId("setting-returns-max-days").fill("");
    await page.getByTestId("setting-pos-idle-lock-minutes").fill("0");
    await page.getByTestId("settings-pos-save").click();
    await expect.poll(() => saved.length).toBe(2);
    expect(saved[1].returns_max_days).toBeNull();
    expect(saved[1].pos_idle_lock_minutes).toBe(0);
  });

  test("values outside the allowed range are rejected on the field, nothing is sent", async ({
    page,
  }) => {
    let posts = 0;
    await page.route("**/api/v1/settings", (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      posts += 1;
      return route.fulfill({ json: { success: true, message: "ok" } });
    });
    await openPosTab(page);

    await page.getByTestId("setting-returns-max-days").fill("0");
    await page.getByTestId("settings-pos-save").click();
    await expect(page.locator("#setting-returns_max_days-error")).toBeVisible();

    await page.getByTestId("setting-returns-max-days").fill("");
    await page.getByTestId("setting-pos-idle-lock-minutes").fill("241");
    await page.getByTestId("settings-pos-save").click();
    await expect(
      page.locator("#setting-pos_idle_lock_minutes-error"),
    ).toBeVisible();
    expect(posts).toBe(0);
  });

  test("a 422 from the server lands under its field", async ({ page }) => {
    await page.route("**/api/v1/settings", (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      return route.fulfill({
        status: 422,
        json: {
          success: false,
          message: "invalid",
          errors: { returns_max_days: ["Server says no"] },
        },
      });
    });
    await openPosTab(page);

    await page.getByTestId("setting-returns-max-days").fill("10");
    await page.getByTestId("settings-pos-save").click();
    await expect(page.locator("#setting-returns_max_days-error")).toHaveText(
      "Server says no",
    );
  });
});

test.describe("SET-02: Telegram token that cannot be read any more", () => {
  const openNotifications = async (page) => {
    await page.goto("/settings", { waitUntil: "networkidle" });
    const mobileTab = page.getByTestId("mobile-tab-notifications");
    if (await mobileTab.isVisible().catch(() => false)) await mobileTab.click();
    else await page.getByTestId("tab-notifications").click();
  };

  test("a re-entry warning shows when the server flags the stored token", async ({ page }) => {
    await page.route("**/api/v1/settings", (route) => {
      if (route.request().method() !== "GET") return route.fallback();
      return patchedJson(route, (body) => {
        body.settings = { ...body.settings, has_telegram_bot_token: false, telegram_bot_token_needs_reentry: true };
      });
    });
    await openNotifications(page);
    await expect(page.getByTestId("telegram-token-reentry")).toBeVisible();
  });

  test("no warning when the flag is absent", async ({ page }) => {
    await openNotifications(page);
    await expect(page.getByTestId("telegram-token-reentry")).toHaveCount(0);
  });
});

test.describe("SET-02: shift rule per branch", () => {
  const mockBranches = async (page, { putStatus, putBody }) => {
    const puts = [];
    await page.route("**/api/v1/stores", (route) => {
      if (route.request().method() !== "GET") return route.fallback();
      return route.fulfill({
        json: {
          success: true,
          stores: [{ id: 71, name: "SET02 Branch", is_active: true }],
          all_users: [],
        },
      });
    });
    await page.route("**/api/v1/stores/71/pos-settings/shift-rule", (route) => {
      if (route.request().method() === "GET") {
        return route.fulfill({
          json: { success: true, data: { require_shift_for_sales: true } },
        });
      }
      puts.push(route.request().postDataJSON());
      return route.fulfill({ status: putStatus, json: putBody });
    });
    return puts;
  };

  test("turning it off while a shift is open shows the server message and keeps it on", async ({
    page,
  }) => {
    const message = "Close the open shift first";
    const puts = await mockBranches(page, {
      putStatus: 422,
      putBody: {
        success: false,
        message,
        error_code: "settings.shift_rule_open_shift",
      },
    });
    await openPosTab(page);

    const toggle = page.getByTestId("shift-rule-switch-71");
    await expect(toggle).toHaveAttribute("aria-checked", "true");
    await toggle.click();

    await expect(page.getByTestId("shift-rule-error-71")).toContainText(
      message,
    );
    await expect(toggle).toHaveAttribute("aria-checked", "true");
    expect(puts).toEqual([{ require_shift_for_sales: false }]);
  });

  test("turning it off with no open shift saves", async ({ page }) => {
    const puts = await mockBranches(page, {
      putStatus: 200,
      putBody: { success: true, data: {} },
    });
    await openPosTab(page);

    const toggle = page.getByTestId("shift-rule-switch-71");
    await toggle.click();
    await expect(toggle).toHaveAttribute("aria-checked", "false");
    await expect(page.getByTestId("shift-rule-error-71")).toHaveCount(0);
    expect(puts).toEqual([{ require_shift_for_sales: false }]);
  });
});

test.describe("SET-02: return window", () => {
  const BUSINESS_DATE = "2026-11-12";

  const source = (id, number, date) => ({
    id,
    number,
    date,
    party_id: 5,
    party_name: "SET02 Customer",
    net_total: "100.000",
  });

  const detail = (s) => ({
    ...s,
    is_walk_in: false,
    cash_refundable_amount: "100.000",
    lines: [
      {
        id: 9001,
        item_id: 1,
        item_name: "SET02 Item",
        item_code: "S02",
        quantity: "2.000",
        returned_quantity: "0.000",
        remaining_quantity: "2.000",
        unit_price: "50.000",
      },
    ],
  });

  const OLD = source(1, "INV-OLD", "2026-11-01"); // 11 days old
  const FRESH = source(2, "INV-FRESH", "2026-11-10"); // 2 days old
  const sources = { 1: detail(OLD), 2: detail(FRESH) };

  /** `cashier` = the signed-in user has no returns.override_window (the admin role always has it). */
  async function setup(page, { cashier = true, onReturn }) {
    await page.addInitScript((asCashier) => {
      if (!asCashier) return;
      try {
        const user = JSON.parse(localStorage.getItem("auth_user") || "{}");
        user.roles = ["cashier"];
        user.permissions = ["returns.manage", "pos.access"];
        localStorage.setItem("auth_user", JSON.stringify(user));
      } catch {
        // no stored user
      }
    }, cashier);

    await page.route("**/api/v1/auth/me", (route) =>
      patchedJson(route, (body) => {
        if (cashier && body.data?.user) {
          body.data.user.roles = ["cashier"];
          body.data.user.permissions = ["returns.manage", "pos.access"];
        }
      }),
    );
    await page.route("**/api/v1/system/context*", (route) =>
      patchedJson(route, (body) => {
        body.data.system.returns_max_days = 7;
        body.data.system.business_date = BUSINESS_DATE;
        body.data.system.timezone = "Africa/Cairo";
        body.data.system.business_day_cutoff = "00:00";
      }),
    );
    await page.route("**/api/v1/return-sources/sales_return?*", (route) =>
      route.fulfill({ json: { success: true, data: [OLD, FRESH] } }),
    );
    await page.route("**/api/v1/return-sources/sales_return", (route) =>
      route.fulfill({ json: { success: true, data: [OLD, FRESH] } }),
    );
    await page.route(
      /\/api\/v1\/return-sources\/sales_return\/\d+$/,
      (route) => {
        const id = route.request().url().split("/").pop();
        return route.fulfill({ json: { success: true, data: sources[id] } });
      },
    );
    await page.route("**/api/v1/returns", (route) => {
      if (route.request().method() !== "POST") return route.fallback();
      return route.fulfill(onReturn(route.request().postDataJSON()));
    });
  }

  async function pickSource(page, number) {
    await page.goto("/returns/create", { waitUntil: "networkidle" });
    await page
      .getByTestId("return-source-option")
      .filter({ hasText: number })
      .click();
    await expect(page.getByTestId("return-line-qty")).toBeVisible();
  }

  async function submitQty(page) {
    await page.getByTestId("return-line-qty").fill("1");
    await page.getByTestId("return-submit").click();
  }

  const fillApproval = async (page, userId, pin) => {
    await page.locator("#approval-manager-user").fill(userId);
    await page.locator("#approval-manager-pin").fill(pin);
    await page.locator(".swal2-confirm").click();
  };

  const expired = {
    status: 422,
    json: {
      success: false,
      message: "Window expired (7 days)",
      error_code: "returns.window_expired",
    },
  };
  const created = {
    status: 201,
    json: { success: true, message: "created", data: { id: 1 } },
  };

  test("inside the window the last day is shown; past it the notice says a manager is needed", async ({
    page,
  }) => {
    await setup(page, { onReturn: () => created });

    await pickSource(page, "INV-FRESH");
    await expect(page.getByTestId("return-window-last-day")).toBeVisible();
    await expect(page.getByTestId("return-window-expired")).toHaveCount(0);

    await page.goto("/returns/create", { waitUntil: "networkidle" });
    await page
      .getByTestId("return-source-option")
      .filter({ hasText: "INV-OLD" })
      .click();
    await expect(page.getByTestId("return-window-expired")).toBeVisible();
    await expect(page.getByTestId("return-window-last-day")).toHaveCount(0);
  });

  test("a user who may override only gets an informative notice", async ({
    page,
  }) => {
    await setup(page, { cashier: false, onReturn: () => created });
    await pickSource(page, "INV-OLD");
    await expect(page.getByTestId("return-window-expired")).toBeVisible();
  });

  test("past the window: PIN dialog, then the same return is re-sent with the override", async ({
    page,
  }) => {
    const sent = [];
    await setup(page, {
      onReturn: (payload) => {
        sent.push(payload);
        return payload.override ? created : expired;
      },
    });
    await pickSource(page, "INV-OLD");
    await submitQty(page);

    await expect(page.locator(".swal2-popup")).toContainText(
      "Window expired (7 days)",
    );
    await fillApproval(page, "12", "654321");

    await expect(page).toHaveURL(/\/returns$/);
    expect(sent).toHaveLength(2);
    expect(sent[0].override).toBeUndefined();
    expect(sent[1].override).toEqual({ user_id: 12, pin: "654321" });
    expect({ ...sent[1], override: undefined }).toEqual({
      ...sent[0],
      override: undefined,
    });
  });

  test("a wrong manager PIN asks again with the server reason", async ({
    page,
  }) => {
    const sent = [];
    await setup(page, {
      onReturn: (payload) => {
        sent.push(payload);
        if (!payload.override) return expired;
        if (payload.override.pin === "111111") {
          return {
            status: 422,
            json: {
              success: false,
              message: "invalid",
              errors: { "override.pin": ["Wrong manager PIN"] },
            },
          };
        }
        return created;
      },
    });
    await pickSource(page, "INV-OLD");
    await submitQty(page);

    await fillApproval(page, "12", "111111");
    await expect(page.locator(".swal2-popup")).toContainText(
      "Wrong manager PIN",
    );
    await fillApproval(page, "12", "222222");

    await expect(page).toHaveURL(/\/returns$/);
    expect(sent.map((p) => p.override?.pin)).toEqual([
      undefined,
      "111111",
      "222222",
    ]);
  });

  test("cancelling the PIN dialog sends nothing more and keeps the form", async ({
    page,
  }) => {
    const sent = [];
    await setup(page, {
      onReturn: (payload) => {
        sent.push(payload);
        return expired;
      },
    });
    await pickSource(page, "INV-OLD");
    await submitQty(page);

    await page.locator(".swal2-cancel").click();
    await expect(page.getByTestId("return-submit")).toBeEnabled();
    await expect(page.getByTestId("return-line-qty")).toHaveValue("1");
    expect(sent).toHaveLength(1);
  });

  test("locked for the day: the server message is shown and no further PIN prompt is offered", async ({
    page,
  }) => {
    const message = "Approvals of this manager are stopped for today";
    const sent = [];
    await setup(page, {
      onReturn: (payload) => {
        sent.push(payload);
        if (!payload.override) return expired;
        return {
          status: 429,
          json: {
            success: false,
            message,
            error_code: "pos.override_locked_today",
          },
        };
      },
    });
    await pickSource(page, "INV-OLD");
    await submitQty(page);
    await fillApproval(page, "12", "111111");

    await expect(page.locator(".swal2-popup")).toContainText(message);
    await expect(page.locator("#approval-manager-pin")).toHaveCount(0);
    expect(sent).toHaveLength(2);
  });
});
