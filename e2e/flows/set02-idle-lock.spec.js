// SET-02: idle lock screen.
//
// A real signed-in session (the project's storage state) against the real API, with three things
// controlled by the test: the idle period (the real /system/context answer is rewritten to 1 minute),
// the clock (page.clock, so no waiting) and the unlock endpoint (a PIN is easier to script than a
// password). Selectors are data-testids and roles, never translated text.
import { test, expect } from "@playwright/test";
import { skipOnPhoneViewport } from "../utils/pos-viewport.js";

const ITEM = {
  id: 998001,
  name: "SET02 Lock Test Beans",
  code: "6229980001111",
  unit: "piece",
  is_weighted: false,
  selling_price: "120.000",
  price_retail: "120.000",
  price_wholesale: "110.000",
  min_selling_price: "100.000",
  current_stock: "50.000",
  is_pos_pinned: true,
  pos_sales_count: 5,
};

const RIGHT_PIN = "123456";
const WRONG_PIN_MESSAGE = "E2E wrong pin message";

async function patchedJson(route, patch) {
  const response = await route.fetch();
  const body = await response.json();
  patch(body);
  return route.fulfill({ response, json: body });
}

/** Idle period `minutes` (0 = off), a POS PIN on the user, the clock under test control. */
async function prepare(page, { minutes = 1, hasPin = true } = {}) {
  await page.addInitScript((pin) => {
    Object.keys(localStorage)
      .filter((k) => k.startsWith("pos_multi_orders"))
      .forEach((k) => localStorage.removeItem(k));
    if (!sessionStorage.getItem("set02-seeded")) {
      localStorage.removeItem("screen_lock_state");
      localStorage.removeItem("screen_lock_activity");
      sessionStorage.setItem("set02-seeded", "1");
    }
    try {
      const user = JSON.parse(localStorage.getItem("auth_user") || "null");
      if (user) {
        user.has_pos_pin = pin;
        localStorage.setItem("auth_user", JSON.stringify(user));
      }
    } catch {
      // no stored user yet
    }
  }, hasPin);

  await page.clock.install();

  await page.route("**/api/v1/system/context*", (route) =>
    patchedJson(route, (body) => {
      body.data.system.pos_idle_lock_minutes = minutes;
    }),
  );
  await page.route("**/api/v1/auth/me", (route) =>
    patchedJson(route, (body) => {
      if (body.data?.user) body.data.user.has_pos_pin = hasPin;
    }),
  );
  await page.route("**/api/v1/auth/unlock-screen", async (route) => {
    const sent = route.request().postDataJSON();
    if (sent.pin === RIGHT_PIN) {
      return route.fulfill({
        json: { success: true, message: "ok", data: { user_id: 1 } },
      });
    }
    return route.fulfill({
      status: 422,
      json: {
        success: false,
        message: WRONG_PIN_MESSAGE,
        errors: { pin: [WRONG_PIN_MESSAGE] },
      },
    });
  });
}

async function mockPos(page, onInvoice) {
  await page.route("**/api/v1/pos/items*", (route) =>
    route.fulfill({
      json: {
        success: true,
        data: [ITEM],
        meta: { current_page: 1, last_page: 1, total: 1 },
      },
    }),
  );
  await page.route("**/api/v1/categories*", (route) =>
    route.fulfill({ json: { success: true, data: [], total_items_count: 1 } }),
  );
  await page.route("**/api/v1/customers*", (route) =>
    route.fulfill({ json: { success: true, data: [] } }),
  );
  await page.route("**/api/v1/shifts/current*", (route) =>
    route.fulfill({
      json: {
        success: true,
        active_shift: { id: 1, shift_number: "SH-1" },
        requires_shift: false,
      },
    }),
  );
  await page.route("**/api/v1/pos/bootstrap*", (route) =>
    route.fulfill({
      json: {
        success: true,
        data: { pos_settings: { effective_max_discount_percent: "10.000" } },
      },
    }),
  );
  await page.route("**/api/v1/invoices", (route) => {
    if (route.request().method() !== "POST") return route.fallback();
    onInvoice?.();
    return route.fulfill({
      status: 500,
      json: { success: false, message: "must not be called while locked" },
    });
  });
}

const IDLE_MS = 90_000;

const lock = (page) => page.getByTestId("screen-lock");

async function waitUntilLocked(page) {
  await page.clock.fastForward(IDLE_MS);
  await expect(lock(page)).toBeVisible();
}

test.describe("SET-02: idle lock screen", () => {
  test("appears after the idle period, covers the app and traps focus", async ({
    page,
  }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });
    await expect(lock(page)).toHaveCount(0);

    await waitUntilLocked(page);

    await expect(page.locator("#app")).toHaveAttribute("inert", "");
    await expect(page.getByTestId("screen-lock-pin")).toBeFocused();

    // Tab never leaves the lock
    for (let i = 0; i < 8; i += 1) {
      await page.keyboard.press("Tab");
      const inside = await page.evaluate(
        () => !!document.activeElement?.closest('[data-testid="screen-lock"]'),
      );
      expect(inside).toBe(true);
    }

    // The lock is the topmost thing at the centre of the screen
    const topmostIsLock = await page.evaluate(() => {
      const el = document.elementFromPoint(
        window.innerWidth / 2,
        window.innerHeight / 2,
      );
      return !!el?.closest('[data-testid="screen-lock"]');
    });
    expect(topmostIsLock).toBe(true);
  });

  test("activity restarts the countdown", async ({ page }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });

    await page.clock.runFor(50_000);
    await page.mouse.move(200, 200);
    await page.mouse.move(260, 240);
    await page.clock.runFor(50_000);
    await expect(lock(page)).toHaveCount(0);

    await page.clock.runFor(30_000);
    await expect(lock(page)).toBeVisible();
  });

  test("0 minutes means the lock is off", async ({ page }) => {
    await prepare(page, { minutes: 0 });
    await page.goto("/", { waitUntil: "networkidle" });
    await page.clock.fastForward(8 * 60 * 60_000);
    await expect(lock(page)).toHaveCount(0);
  });

  test("is never shown on the sign-in page", async ({ page }) => {
    await prepare(page);
    await page.addInitScript(() => {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("auth_user");
    });
    await page.goto("/login", { waitUntil: "domcontentloaded" });
    await page.clock.fastForward(IDLE_MS);
    await expect(lock(page)).toHaveCount(0);
  });

  test("survives a reload (F5 does not bypass it)", async ({ page }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.reload({ waitUntil: "domcontentloaded" });
    await expect(lock(page)).toBeVisible();
    await expect(page.locator("#app")).toHaveAttribute("inert", "");
  });

  test("a locked terminal stays locked on print routes (typed URL and in-app navigation)", async ({ page }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    const expectCovered = async () => {
      await expect(lock(page)).toBeVisible();
      await expect(page.locator("#app")).toHaveAttribute("inert", "");
      const topmostIsLock = await page.evaluate(() => {
        const el = document.elementFromPoint(window.innerWidth / 2, window.innerHeight / 2);
        return !!el?.closest('[data-testid="screen-lock"]');
      });
      expect(topmostIsLock).toBe(true);
    };

    await page.evaluate(() => window.spaRouter.push("/invoices/1/print"));
    await expect(page).toHaveURL(/\/invoices\/1\/print/);
    await expectCovered();

    await page.goto("/invoices/2/print", { waitUntil: "domcontentloaded" });
    await expectCovered();
  });

  test("a PIN locked for the day: server message, password tab only, no more PIN tries", async ({ page }) => {
    const message = "PIN locked until tomorrow";
    let pinCalls = 0;
    await prepare(page);
    await page.route("**/api/v1/auth/unlock-screen", (route) => {
      const sent = route.request().postDataJSON();
      if (sent.pin) {
        pinCalls += 1;
        return route.fulfill({
          status: 429,
          json: { success: false, message, error_code: "pos.override_locked_today" },
        });
      }
      return route.fulfill({ json: { success: true, message: "ok", data: { user_id: 1 } } });
    });
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.getByTestId("screen-lock-pin").fill("123456");
    await page.getByTestId("screen-lock-submit").click();

    await expect(page.getByTestId("screen-lock-error")).toHaveText(message);
    await expect(page.getByTestId("screen-lock-tab-pin")).toBeDisabled();
    await expect(page.getByTestId("screen-lock-password")).toBeVisible();
    expect(pinCalls).toBe(1);

    await page.getByTestId("screen-lock-password").fill("whatever-password");
    await page.getByTestId("screen-lock-submit").click();
    await expect(lock(page)).toHaveCount(0);
  });

  test("a wrong PIN shows the server message and stays locked", async ({
    page,
  }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.getByTestId("screen-lock-pin").fill("000000");
    await page.getByTestId("screen-lock-submit").click();

    await expect(page.getByTestId("screen-lock-error")).toHaveText(
      WRONG_PIN_MESSAGE,
    );
    await expect(lock(page)).toBeVisible();
    await expect(page.getByTestId("screen-lock-pin")).toHaveValue("");
  });

  test("a PIN that is not 4 to 6 digits is rejected before any request", async ({
    page,
  }) => {
    await prepare(page);
    let unlockCalls = 0;
    await page.route("**/api/v1/auth/unlock-screen", (route) => {
      unlockCalls += 1;
      return route.fallback();
    });
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.getByTestId("screen-lock-pin").fill("12");
    await page.getByTestId("screen-lock-submit").click();

    await expect(page.getByTestId("screen-lock-error")).toBeVisible();
    expect(unlockCalls).toBe(0);
  });

  test("the right PIN unlocks and the countdown starts again", async ({
    page,
  }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.getByTestId("screen-lock-pin").fill(RIGHT_PIN);
    await page.getByTestId("screen-lock-submit").click();

    await expect(lock(page)).toHaveCount(0);
    await expect(page.locator("#app")).not.toHaveAttribute("inert", /.*/);

    await page.clock.fastForward(IDLE_MS);
    await expect(lock(page)).toBeVisible();
  });

  test("a user without a PIN only gets the password method", async ({
    page,
  }) => {
    await prepare(page, { hasPin: false });
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await expect(page.getByTestId("screen-lock-tab-pin")).toHaveCount(0);
    await expect(page.getByTestId("screen-lock-password")).toBeVisible();
    await expect(page.getByTestId("screen-lock-password")).toHaveAttribute(
      "autocomplete",
      "current-password",
    );
  });

  test("switch user asks first, then signs out", async ({ page }) => {
    await prepare(page);
    let loggedOut = false;
    await page.route("**/api/v1/auth/logout", (route) => {
      loggedOut = true;
      return route.fulfill({ json: { success: true, message: "ok" } });
    });
    await page.goto("/", { waitUntil: "networkidle" });
    await waitUntilLocked(page);

    await page.getByTestId("screen-lock-switch-user").click();
    expect(loggedOut).toBe(false);
    await page.getByTestId("screen-lock-switch-confirm-button").click();

    await expect(page).toHaveURL(/\/login/);
    await expect(lock(page)).toHaveCount(0);
    expect(loggedOut).toBe(true);
  });

  test("locking in one tab locks the other, unlocking releases both", async ({
    page,
    context,
  }) => {
    await prepare(page);
    await page.goto("/", { waitUntil: "networkidle" });

    const other = await context.newPage();
    await other.addInitScript(() =>
      sessionStorage.setItem("set02-seeded", "1"),
    );
    await other.route("**/api/v1/system/context*", (route) =>
      patchedJson(route, (body) => {
        body.data.system.pos_idle_lock_minutes = 1;
      }),
    );
    await other.route("**/api/v1/auth/unlock-screen", (route) =>
      route.fulfill({
        json: { success: true, message: "ok", data: { user_id: 1 } },
      }),
    );
    await other.goto("/", { waitUntil: "networkidle" });
    await expect(lock(other)).toHaveCount(0);

    await waitUntilLocked(page);
    await expect(lock(other)).toBeVisible();

    await page.getByTestId("screen-lock-pin").fill(RIGHT_PIN);
    await page.getByTestId("screen-lock-submit").click();
    await expect(lock(page)).toHaveCount(0);
    await expect(lock(other)).toHaveCount(0);
  });
});

test.describe("SET-02: the lock on the POS", () => {
  skipOnPhoneViewport(test);

  test("shortcuts and scanner input do nothing while locked, and the cart survives the unlock", async ({
    page,
  }) => {
    let invoiceCalls = 0;
    await prepare(page);
    await mockPos(page, () => {
      invoiceCalls += 1;
    });
    await page.goto("/pos", { waitUntil: "networkidle" });
    await expect(page.getByTestId("pos-search-input")).toBeVisible();

    await page.getByText(ITEM.name, { exact: true }).first().click();
    const qty = page.locator('[data-testid="pos-cart-qty"]:visible');
    await expect(qty).toHaveCount(1);
    await qty.first().fill("3");
    await qty.first().blur();
    await expect(qty.first()).toHaveValue("3");

    await waitUntilLocked(page);

    // F2 (search), F9 (checkout), Ctrl+Enter (checkout) and a scanner typing digits + Enter
    await page.keyboard.press("F2");
    await expect(page.getByTestId("screen-lock-pin")).toBeFocused();
    await page.keyboard.press("F9");
    await page.keyboard.press("Control+Enter");
    await page.keyboard.type(ITEM.code);
    await page.keyboard.press("Enter");
    expect(invoiceCalls).toBe(0);
    await expect(page.getByTestId("pos-search-input")).toHaveValue("");

    // The lock only got what was typed into its own field; clear it and unlock
    await page.getByTestId("screen-lock-pin").fill(RIGHT_PIN);
    await page.getByTestId("screen-lock-submit").click();
    await expect(lock(page)).toHaveCount(0);

    // Same cart, same line, same quantity: nothing was reloaded or rebuilt
    await expect(
      page.locator('[data-testid="pos-cart-qty"]:visible'),
    ).toHaveCount(1);
    await expect(
      page.locator('[data-testid="pos-cart-qty"]:visible').first(),
    ).toHaveValue("3");
  });
});
