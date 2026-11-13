import { test, expect } from "@playwright/test";

/**
 * SELL-02: permanent subscription banner.
 * The API is mocked: the banner comes from /system/context `subscription_banner`; the server decides who
 * gets one (cashiers and healthy subscriptions get null), the SPA only renders it.
 */

test.use({ storageState: { cookies: [], origins: [] } });

const WORKSPACE = process.env.E2E_WORKSPACE_CODE || "demo";

const makeUser = (role) => ({
  id: 902,
  name: "E2E User",
  email: null,
  phone: "01099900002",
  roles: [role],
  permissions: [],
  theme_preference: "dark",
  locale: "ar",
  is_active: true,
  must_change_password: false,
  default_store_id: 1,
});

const json = (route, body, status = 200) =>
  route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });

async function open(page, role, banner) {
  const user = makeUser(role);
  await page.addInitScript(
    ({ workspace, user }) => {
      localStorage.setItem("active_tenant", workspace);
      localStorage.setItem("tenant_id", workspace);
      localStorage.setItem("auth_token", "e2e-token");
      localStorage.setItem("auth_user", JSON.stringify(user));
    },
    { workspace: WORKSPACE, user },
  );
  await page.route("**/api/v1/**", (route) =>
    json(route, { success: true, data: [], meta: {}, pagination: {} }),
  );
  await page.route("**/api/v1/system/context**", (route) =>
    json(route, {
      success: true,
      data: {
        system: { system_theme_color: "ibraa" },
        locale: "ar",
        subscription_banner: banner,
      },
    }),
  );
  await page.route("**/api/v1/auth/me**", (route) =>
    json(route, { success: true, data: { user } }),
  );
  await page.goto("/");
}

test.describe("Subscription banner (SELL-02)", () => {
  test("admin on a trial with 3 days left sees the banner and its action", async ({
    page,
  }) => {
    await open(page, "admin", {
      state: "trial",
      kind: "trial",
      days_left: 3,
      ends_at: null,
      grace_ends_at: null,
      reason: null,
      action_path: "/billing",
    });
    const banner = page.getByTestId("subscription-banner");
    await expect(banner).toBeVisible();
    await expect(banner).toHaveAttribute("data-state", "trial");
    await expect(page.getByTestId("subscription-banner-action")).toBeVisible();
  });

  test("cashier gets no banner (server sends null)", async ({ page }) => {
    await open(page, "cashier", null);
    await expect(page.getByTestId("subscription-banner")).toHaveCount(0);
  });

  test("healthy paid tenant gets no banner", async ({ page }) => {
    await open(page, "admin", null);
    await expect(page.getByTestId("subscription-banner")).toHaveCount(0);
  });
});
