import { test, expect } from "@playwright/test";

/**
 * AUTH-1b: testing-only quick login on the login screen.
 *
 * The first two cases mock /auth/options (and the user list) so they are deterministic
 * whatever the local QUICK_LOGIN_ENABLED value is. The last case hits the real API and
 * runs only when E2E_QUICK_LOGIN=1 and the local server has QUICK_LOGIN_ENABLED=true.
 */

const workspaceCode = process.env.E2E_WORKSPACE_CODE || "2m";

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeEach(async ({ page }) => {
  await page.addInitScript((code) => {
    localStorage.setItem("active_tenant", code);
    localStorage.setItem("tenant_id", code);
  }, workspaceCode);
});

const mockOptions = (page, quickLogin) =>
  page.route("**/api/v1/auth/options", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: { quick_login: quickLogin, default_method: "password" },
      }),
    }),
  );

test("quick login control is absent when the server disables it", async ({
  page,
}) => {
  await mockOptions(page, false);
  await page.goto("/login");

  await expect(page.locator('input[type="password"]').first()).toBeVisible();
  await expect(page.getByTestId("login-method-quick")).toHaveCount(0);
  await expect(page.getByTestId("quick-login-panel")).toHaveCount(0);
});

test("password stays the default method when quick login is enabled", async ({
  page,
}) => {
  await mockOptions(page, true);
  let usersRequested = false;
  await page.route("**/api/v1/auth/quick-login/users", (route) => {
    usersRequested = true;
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: [{ id: 7, name: "E2E Cashier" }],
      }),
    });
  });

  await page.goto("/login");

  await expect(page.getByTestId("login-method-password")).toHaveAttribute(
    "aria-selected",
    "true",
  );
  await expect(page.locator('input[type="password"]').first()).toBeVisible();
  expect(usersRequested).toBe(false);

  await page.getByTestId("login-method-quick").click();
  await expect(page.getByTestId("quick-login-testing-badge")).toBeVisible();
  await expect(page.getByTestId("quick-login-users")).toContainText(
    "E2E Cashier",
  );
  expect(usersRequested).toBe(true);
});

test("picking a user logs in and lands on the dashboard", async ({ page }) => {
  test.skip(
    process.env.E2E_QUICK_LOGIN !== "1",
    "needs a local server with QUICK_LOGIN_ENABLED=true",
  );

  await page.goto("/login");
  await expect(page.getByTestId("login-method-password")).toHaveAttribute(
    "aria-selected",
    "true",
  );

  await page.getByTestId("login-method-quick").click();
  await page.getByTestId("quick-login-users").locator("button").first().click();

  await page.waitForURL((url) => !url.pathname.includes("/login"), {
    timeout: 15000,
  });
  expect(new URL(page.url()).pathname).not.toContain("/login");
});
