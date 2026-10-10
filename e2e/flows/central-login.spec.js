import { test, expect } from "@playwright/test";

/**
 * IDEN-1.9: platform console on central auth, with a mocked API (page.route), so it needs
 * the app shell only (no central user, no 2FA secret, no tenant DB).
 *
 * Central mode is what the admin host renders: <meta name="app-context" content="central">.
 * The test injects that tag into the served HTML, like app.blade.php does on the admin host.
 *
 *  1. guest -> /super-admin/tenants redirects to the central login (redirect kept);
 *  2. email + password -> 2FA challenge -> code -> back on the tenants list;
 *  3. every console request: own Bearer token, never X-Tenant / X-Store-Id, never a tenant endpoint;
 *  4. logout revokes the token, clears the central storage keys and lands on the central login;
 *  5. tenant mode (no meta tag): /super-admin/* does not exist.
 */

const CENTRAL_TOKEN = "central-test-token";
const OPERATOR = {
  id: 1,
  name: "Platform Operator",
  email: "operator@example.test",
  is_active: true,
  two_factor_enabled: true,
  last_login_at: null,
  roles: ["super_admin"],
  permissions: [],
};
const TENANT = {
  id: "acme",
  name: "Acme Coffee",
  domain: "acme.example.test",
  plan_name: "Pro",
  email: "owner@acme.example.test",
  phone: null,
  status: "active",
};

const json = (route, status, body) =>
  route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });

async function serveCentralShell(page) {
  await page.route(
    (url) => url.pathname.startsWith("/super-admin"),
    async (route) => {
      if (route.request().resourceType() !== "document")
        return route.fallback();
      const response = await route.fetch();
      let html = await response.text();
      if (!html.includes('name="app-context"')) {
        html = html.replace(
          "<head>",
          '<head>\n    <meta name="app-context" content="central">',
        );
      }
      return route.fulfill({
        response,
        body: html,
        headers: { ...response.headers(), "content-type": "text/html" },
      });
    },
  );
}

async function mockCentralApi(page, log) {
  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname.replace(/^\/api\/v1/, "");
    const headers = request.headers();
    log.push({ method: request.method(), path, headers });

    if (path === "/super-admin/auth/login" && request.method() === "POST") {
      const body = request.postDataJSON();
      if (
        body.email !== OPERATOR.email ||
        body.password !== "correct-password"
      ) {
        return json(route, 422, {
          message: "invalid",
          errors: { email: ["invalid"] },
        });
      }
      return json(route, 200, {
        success: true,
        data: {
          two_factor_required: true,
          challenge_id: "challenge-1",
          challenge_expires_at: null,
        },
      });
    }

    if (
      path === "/super-admin/auth/two-factor-challenge" &&
      request.method() === "POST"
    ) {
      const body = request.postDataJSON();
      if (body.challenge_id !== "challenge-1" || body.code !== "123456") {
        return json(route, 422, {
          message: "bad code",
          errors: { code: ["bad code"] },
        });
      }
      return json(route, 200, {
        success: true,
        data: {
          two_factor_required: false,
          two_factor_setup_required: false,
          token: CENTRAL_TOKEN,
          token_type: "Bearer",
          expires_at: new Date(Date.now() + 4 * 3600 * 1000).toISOString(),
          abilities: ["central:*"],
          user: OPERATOR,
        },
      });
    }

    const authorized = headers.authorization === `Bearer ${CENTRAL_TOKEN}`;

    if (path === "/super-admin/auth/me") {
      return authorized
        ? json(route, 200, { success: true, data: OPERATOR })
        : json(route, 401, { message: "no" });
    }
    if (path === "/super-admin/auth/logout") {
      return json(route, 200, { success: true, message: "bye" });
    }
    if (path === "/super-admin/tenants") {
      if (!authorized) return json(route, 401, { message: "no" });
      return json(route, 200, {
        success: true,
        tenants: { data: [TENANT] },
        plans: [{ id: 1, name: "Pro" }],
      });
    }

    return json(route, 200, { success: true, data: {} });
  });
}

test.describe("Flow: platform console on central auth (IDEN-1.9)", () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test("login + 2FA challenge -> tenants list -> logout, with no tenant headers", async ({
    page,
  }) => {
    const log = [];
    await serveCentralShell(page);
    await mockCentralApi(page, log);

    await page.goto("/super-admin/tenants", { waitUntil: "domcontentloaded" });

    await expect(page).toHaveURL(/\/super-admin\/login\?redirect=/);
    await page.locator('input[name="email"]').fill(OPERATOR.email);
    await page.locator('input[name="password"]').fill("correct-password");
    await page.locator('button[type="submit"]').click();

    const codeInput = page.locator('input[name="code"]');
    await expect(codeInput).toBeVisible();
    await codeInput.fill("123456");
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/super-admin\/tenants$/);
    await expect(page.getByText(TENANT.name).first()).toBeVisible();

    const storage = await page.evaluate(() => ({
      central: localStorage.getItem("central_auth_token"),
      tenant: localStorage.getItem("auth_token"),
    }));
    expect(storage.central).toBe(CENTRAL_TOKEN);
    expect(storage.tenant).toBeNull();

    await page.getByTestId("central-logout").click();
    await expect(page).toHaveURL(/\/super-admin\/login/);
    expect(
      await page.evaluate(() => localStorage.getItem("central_auth_token")),
    ).toBeNull();
    expect(log.some((entry) => entry.path === "/super-admin/auth/logout")).toBe(
      true,
    );

    // Logged out: the console is closed again.
    await page.goto("/super-admin/dashboard", {
      waitUntil: "domcontentloaded",
    });
    await expect(page).toHaveURL(/\/super-admin\/login\?redirect=/);

    const withTenantHeaders = log.filter(
      (entry) => entry.headers["x-tenant"] || entry.headers["x-store-id"],
    );
    expect(
      withTenantHeaders,
      "no console request may carry X-Tenant / X-Store-Id",
    ).toEqual([]);

    const tenantEndpoints = log.filter((entry) =>
      [
        "/auth/login",
        "/auth/me",
        "/auth/logout",
        "/system/context",
        "/system/translations",
      ].some((p) => entry.path.startsWith(p)),
    );
    expect(tenantEndpoints, "the console never calls tenant endpoints").toEqual(
      [],
    );

    const tenantsCall = log.find(
      (entry) => entry.path === "/super-admin/tenants",
    );
    expect(tenantsCall?.headers.authorization).toBe(`Bearer ${CENTRAL_TOKEN}`);
  });

  test("tenant mode has no /super-admin routes", async ({ page }) => {
    await page.route("**/api/v1/**", (route) =>
      json(route, 200, { success: true, data: {} }),
    );

    await page.goto("/super-admin/dashboard", {
      waitUntil: "domcontentloaded",
    });

    await expect(page).not.toHaveURL(/\/super-admin/);
    await expect(page.locator('[data-testid="central-logout"]')).toHaveCount(0);
  });
});
