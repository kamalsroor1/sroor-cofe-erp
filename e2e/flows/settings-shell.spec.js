/* global window, document */
import { test, expect } from "@playwright/test";
import fs from "fs";

/**
 * Settings Shell E2E Spec (SETG-5 / Lane C)
 * Covers:
 * - Search -> Highlight with pulse ring
 * - Deep Link directly activating tab and highlighting field
 * - Save Cutoff -> 200 persistence
 * - Invalid Cutoff -> 422 translated error under field
 * - Unsaved Changes dirty warning (router leave guard)
 * - Mobile View Hub drill-down and back navigation
 * - Dark / Light theme toggles & dir="rtl"
 * - Zero Console Errors
 * - 500 Server Error Graceful Recovery (mocked only for 500 per Mocking Policy)
 */

const IGNORED_CONSOLE = [
  /favicon/i,
  /net::ERR_ABORTED/i,
  /ERR_CONNECTION_REFUSED/i,
  /Download the Vue Devtools/i,
  /Failed to load resource/i,
];

function setupConsoleListener(page) {
  const errors = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      const text = msg.text();
      if (!IGNORED_CONSOLE.some((re) => re.test(text))) {
        errors.push(text);
      }
    }
  });
  page.on("pageerror", (err) => {
    errors.push(err.message);
  });
  return errors;
}

const dismissAnyModal = async (page) => {
  const modalBtn = page
    .locator(
      'button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية"), button:has-text("بدء الوردية")',
    )
    .first();
  if (await modalBtn.isVisible({ timeout: 1500 }).catch(() => false)) {
    await modalBtn.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);
  }
};

test.describe("Settings Shell & Workflow Verification (settings-shell.spec.js)", () => {
  // If auth state file exists, use it
  const authStatePath = "e2e/.auth/user.json";
  if (fs.existsSync(authStatePath)) {
    test.use({ storageState: authStatePath });
  }

  test.beforeEach(async ({ page }) => {
    // Set standard desktop viewport by default
    await page.setViewportSize({ width: 1280, height: 800 });
  });

  test("1. Search -> Highlight: typing query, selecting result opens tab and highlights field", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // Ensure settings header loaded
    const searchInput = page.locator('header input[type="text"]').first();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Type query for business day cutoff
    await searchInput.fill("بداية اليوم التجاري");
    await page.waitForTimeout(300);

    // Dropdown results list should appear
    const resultItem = page
      .locator('header button:has-text("بداية اليوم التجاري")')
      .first();
    await expect(resultItem).toBeVisible({ timeout: 5000 });

    // Click search result
    await resultItem.click();

    // Should activate General tab and scroll to cutoff setting
    const cutoffField = page.locator("#setting-business_day_cutoff");
    await expect(cutoffField).toBeVisible({ timeout: 5000 });

    // Element should receive pulse ring classes
    await expect(cutoffField).toHaveClass(/ring-theme-primary|ring-4/, {
      timeout: 5000,
    });

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("2. Deep Link: direct navigation with ?k=locale.business_day_cutoff activates tab and highlights field", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings?k=locale.business_day_cutoff", {
      waitUntil: "networkidle",
    });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // General tab should be active and target field highlighted
    const cutoffField = page.locator("#setting-business_day_cutoff");
    await expect(cutoffField).toBeVisible({ timeout: 10000 });
    await expect(cutoffField).toHaveClass(/ring-theme-primary|ring-4/, {
      timeout: 5000,
    });

    // Test deep link with shorter key format ?k=business_day_cutoff
    await page.goto("/settings?k=business_day_cutoff", {
      waitUntil: "networkidle",
    });
    await dismissAnyModal(page);
    await expect(cutoffField).toBeVisible({ timeout: 10000 });
    await expect(cutoffField).toHaveClass(/ring-theme-primary|ring-4/, {
      timeout: 5000,
    });

    // Test deep link to another tab (e.g. printing tab ?k=receipt.footer_text)
    await page.goto("/settings?k=receipt.footer_text", {
      waitUntil: "networkidle",
    });
    await dismissAnyModal(page);
    const footerNoteField = page.locator("#setting-invoice_footer_note");
    await expect(footerNoteField).toBeVisible({ timeout: 10000 });
    await expect(footerNoteField).toHaveClass(/ring-theme-primary|ring-4/, {
      timeout: 5000,
    });

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("3. Save Cutoff -> 200: entering valid cutoff (03:00) saves and persists value", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    const cutoffInput = page.locator("#setting-business_day_cutoff");
    await expect(cutoffInput).toBeVisible({ timeout: 10000 });

    // Enter valid cutoff: 03:00
    await cutoffInput.fill("03:00");
    await page.waitForTimeout(200);

    // Click Save button (in header or section footer)
    const saveBtn = page
      .locator('header button:has-text("حفظ"), main button:has-text("حفظ")')
      .first();
    await expect(saveBtn).toBeVisible();

    const saveResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings") &&
        res.request().method() === "POST",
    );

    await saveBtn.click();
    const saveRes = await saveResponsePromise;
    expect(saveRes.status()).toBe(200);

    // Success notification should be visible
    const swalSuccess = page.locator(
      '.swal2-success, .swal2-popup:has-text("نجاح"), .swal2-popup:has-text("تم")',
    );
    await expect(swalSuccess.first()).toBeVisible({ timeout: 5000 });

    // Wait for modal to disappear or dismiss
    await page.waitForTimeout(1600);

    // Reload page to verify persisted value from API
    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    const reloadedCutoff = page.locator("#setting-business_day_cutoff");
    await expect(reloadedCutoff).toHaveValue("03:00", { timeout: 10000 });

    // Restore to 00:00 to leave database in clean default state
    await reloadedCutoff.fill("00:00");
    const restoreSavePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings") &&
        res.request().method() === "POST",
    );
    await page
      .locator('header button:has-text("حفظ"), main button:has-text("حفظ")')
      .first()
      .click();
    const restoreRes = await restoreSavePromise;
    expect(restoreRes.status()).toBe(200);

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("4. Invalid Cutoff -> 422 under field: entering invalid cutoff (25:99) triggers 422 translated error", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    const cutoffInput = page.locator("#setting-business_day_cutoff");
    await expect(cutoffInput).toBeVisible({ timeout: 10000 });

    // Force invalid value '25:99' into the input to test real backend validation
    await cutoffInput.evaluate((el) => {
      el.type = "text";
      el.value = "25:99";
      el.dispatchEvent(new Event("input", { bubbles: true }));
    });

    // Click Save button
    const saveBtn = page
      .locator('header button:has-text("حفظ"), main button:has-text("حفظ")')
      .first();
    const saveResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings") &&
        res.request().method() === "POST",
    );

    await saveBtn.click();
    const saveRes = await saveResponsePromise;
    expect(saveRes.status()).toBe(422);

    // Translated 422 error must appear directly under the cutoff field
    const translatedError = page.locator(
      "text=بداية اليوم التجاري لازم تكون بالشكل HH:MM",
    );
    await expect(translatedError.first()).toBeVisible({ timeout: 5000 });

    // Dismiss the Swal error toast if open
    const swalConfirm = page
      .locator(".swal2-confirm, .swal2-popup button")
      .first();
    if (await swalConfirm.isVisible({ timeout: 1000 }).catch(() => false)) {
      await swalConfirm.click().catch(() => {});
    }

    // Restore input type and valid value
    await cutoffInput.evaluate((el) => {
      el.type = "time";
      el.value = "00:00";
      el.dispatchEvent(new Event("input", { bubbles: true }));
    });

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("5. Unsaved Changes Warning: modifying field and navigating away triggers confirmation prompt", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // Modify a field to dirty the form
    const companyNameInput = page.locator("#setting-company_name");
    await expect(companyNameInput).toBeVisible({ timeout: 10000 });
    const originalValue = await companyNameInput.inputValue();
    await companyNameInput.fill(originalValue + " - تعديل مؤقت");

    // Unsaved changes indicator badge should be visible
    const dirtyBadge = page.locator(
      'header span:has-text("تغييرات غير محفوظة"), span:has-text("Unsaved")',
    );
    await expect(dirtyBadge.first()).toBeVisible({ timeout: 5000 });

    // Attach dialog handler to intercept window.confirm from Vue Router onBeforeRouteLeave
    let dialogTriggered = false;
    let dialogText = "";
    page.once("dialog", async (dialog) => {
      dialogTriggered = true;
      dialogText = dialog.message();
      await dialog.dismiss(); // Cancel navigation
    });

    // Trigger router navigation away to dashboard or pos
    const appNavigated = await page.evaluate(() => {
      if (window.__vue_router__) {
        window.__vue_router__.push("/pos");
        return true;
      }
      return false;
    });

    if (!appNavigated) {
      // Fallback: Click sidebar navigation link
      const sidebarPosLink = page
        .locator('aside a[href*="/pos"], nav a[href*="/pos"]')
        .first();
      if (
        await sidebarPosLink.isVisible({ timeout: 2000 }).catch(() => false)
      ) {
        await sidebarPosLink.click();
      }
    }

    await page.waitForTimeout(500);

    // Confirmation warning should have fired
    expect(dialogTriggered).toBe(true);
    expect(dialogText).toContain("تغييرات غير محفوظة");

    // Since dialog was dismissed, we should remain on /settings
    expect(page.url()).toContain("/settings");

    // Restore original value
    await companyNameInput.fill(originalValue);

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("6. Mobile View (375x667): renders Mobile Hub cards, drill-down detail view, and ArrowRight back button", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    // Set mobile viewport 375x667 (iPhone standard)
    await page.setViewportSize({ width: 375, height: 667 });

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // On mobile, initial view is the Hub Grid with tab cards
    const hubCards = page.locator("nav button");
    await expect(hubCards.first()).toBeVisible({ timeout: 10000 });

    // Verify tabs exist in Mobile Hub
    await expect(
      page.locator('nav button:has-text("عام")').first(),
    ).toBeVisible();
    await expect(
      page.locator('nav button:has-text("الطباعة")').first(),
    ).toBeVisible();
    await expect(
      page.locator('nav button:has-text("المخزون")').first(),
    ).toBeVisible();
    await expect(
      page.locator('nav button:has-text("الإشعارات")').first(),
    ).toBeVisible();
    await expect(
      page.locator('nav button:has-text("الجهاز والنظام")').first(),
    ).toBeVisible();

    // Click a tab card (e.g. Printing) to drill-down into detail view
    await page.locator('nav button:has-text("الطباعة")').first().click();
    await page.waitForTimeout(400);

    // Section detail should be visible
    await expect(page.locator("#setting-show_print_company_name")).toBeVisible({
      timeout: 5000,
    });

    // Back button with ArrowRight icon should be visible in header
    const backBtn = page
      .locator(
        'header button[title*="الرجوع"], header button:has(svg.lucide-arrow-right)',
      )
      .first();
    await expect(backBtn).toBeVisible({ timeout: 5000 });

    // Click Back button to return to Mobile Hub
    await backBtn.click();
    await page.waitForTimeout(400);

    // Mobile Hub cards grid should be displayed again
    await expect(
      page.locator('nav button:has-text("عام")').first(),
    ).toBeVisible({ timeout: 5000 });

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test('7. Dark / Light Theme & RTL: dir="rtl" verified and theme mode switches classes correctly', async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // 1. Verify RTL direction
    const htmlDir = await page.getAttribute("html", "dir");
    expect(htmlDir).toBe("rtl");

    // 2. Navigate to Device Tab
    const deviceTabBtn = page
      .locator('button:has-text("الجهاز والنظام"), button:has-text("Device")')
      .first();
    await expect(deviceTabBtn).toBeVisible({ timeout: 10000 });
    await deviceTabBtn.click();
    await page.waitForTimeout(400);

    // 3. Theme mode toggle buttons in SettingsAppearanceSection
    const darkModeBtn = page
      .locator(
        '#setting-theme_mode button:has-text("داكن"), #setting-theme_mode button:has-text("Dark")',
      )
      .first();
    const lightModeBtn = page
      .locator(
        '#setting-theme_mode button:has-text("فاتح"), #setting-theme_mode button:has-text("Light")',
      )
      .first();
    await expect(darkModeBtn).toBeVisible({ timeout: 5000 });
    await expect(lightModeBtn).toBeVisible({ timeout: 5000 });

    // Switch to Dark mode
    await darkModeBtn.click();
    await page.waitForTimeout(300);
    const isDark = await page.evaluate(() =>
      document.documentElement.classList.contains("dark"),
    );
    expect(isDark).toBe(true);

    // Switch to Light mode
    await lightModeBtn.click();
    await page.waitForTimeout(300);
    const isStillDark = await page.evaluate(() =>
      document.documentElement.classList.contains("dark"),
    );
    expect(isStillDark).toBe(false);

    // Zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("8. Existing Sections Wiring: all tabs (General, Printing, Inventory, Notifications, Device) mount cleanly", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // 1. General Tab (default)
    await expect(page.locator("#setting-company_name")).toBeVisible({
      timeout: 10000,
    });
    await expect(page.locator("#setting-currency")).toBeVisible();
    await expect(page.locator("#setting-timezone")).toBeVisible();
    await expect(page.locator("#setting-business_day_cutoff")).toBeVisible();

    // 2. Printing Tab
    await page.locator('button:has-text("الطباعة")').first().click();
    await expect(page.locator("#setting-show_print_company_name")).toBeVisible({
      timeout: 5000,
    });
    await expect(page.locator("#setting-print_show_qr")).toBeVisible();
    await expect(page.locator("#setting-invoice_footer_note")).toBeVisible();

    // 3. Inventory Tab
    await page.locator('button:has-text("المخزون")').first().click();
    await expect(
      page.locator("#setting-low_stock_default_threshold"),
    ).toBeVisible({ timeout: 5000 });
    await expect(page.locator("#setting-inventory_units")).toBeVisible();

    // 4. Notifications Tab
    await page.locator('button:has-text("الإشعارات")').first().click();
    await expect(
      page.locator("#setting-telegram_notifications_enabled"),
    ).toBeVisible({ timeout: 5000 });
    await expect(page.locator("#setting-telegram_bot_token")).toBeVisible();
    await expect(page.locator("#setting-telegram_chat_id")).toBeVisible();

    // 5. Device Tab
    await page.locator('button:has-text("الجهاز والنظام")').first().click();
    await expect(page.locator("#setting-system_theme_color")).toBeVisible({
      timeout: 5000,
    });
    await expect(page.locator("#setting-theme_mode")).toBeVisible();
    await expect(page.locator("#setting-database_backup")).toBeVisible();

    // Zero console errors across all sections
    expect(consoleErrors).toHaveLength(0);
  });

  test("9. Mocking Policy: 500 Server Error handles gracefully without crashing SPA", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    // Intercept POST /api/v1/settings only to test 500 error per Mocking Policy
    await page.route("**/api/v1/settings", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 500,
          contentType: "application/json",
          body: JSON.stringify({
            message: "Internal Server Error (Simulated 500)",
          }),
        });
      } else {
        await route.continue();
      }
    });

    // Click Save button
    const saveBtn = page
      .locator('header button:has-text("حفظ"), main button:has-text("حفظ")')
      .first();
    await saveBtn.click();

    // Error notification popup should appear gracefully
    const errorModal = page.locator(
      '.swal2-error, .swal2-popup:has-text("خطأ"), .swal2-popup:has-text("Error")',
    );
    await expect(errorModal.first()).toBeVisible({ timeout: 5000 });

    // Close the modal
    const confirmBtn = page
      .locator(".swal2-confirm, .swal2-popup button")
      .first();
    if (await confirmBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmBtn.click().catch(() => {});
    }

    // Clean up mock route
    await page.unroute("**/api/v1/settings");

    // SPA remains intact and interactive
    await expect(page.locator("#setting-company_name")).toBeVisible();

    // Assert zero console errors
    expect(consoleErrors).toHaveLength(0);
  });

  test("10. Branding: upload a PNG logo -> preview shows", async ({ page }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings?k=branding", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    const logoSection = page.locator("#setting-logo_light");
    await expect(logoSection).toBeVisible({ timeout: 10000 });

    const fileInput = page.locator('[data-testid="logo-light-input"]');

    // 64x64 valid PNG (satisfies backend dimension rule: 64-2048 px)
    const validPngBuffer = Buffer.from(
      "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAENSURBVHja7dsxDkJACEThVrwA1/BG3M7bUFiJjVHQfJk82WwWb5iZmZksd3f3fX9j5pznOeesuR7d3f1/gG3bxj/e3ff9v3POufd+f+77/nzf9/M8T3wQ+D0AEAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAgQCBCAQIAABAIEAgQCBAIEAgQCBAIEAgQChAG7mZmv2J8CjLg1z0QAAAAASUVORK5CYII=",
      "base64",
    );

    const uploadResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings/branding/logo/light") &&
        res.request().method() === "POST",
    );

    await fileInput.setInputFiles({
      name: "test-shop-logo.png",
      mimeType: "image/png",
      buffer: validPngBuffer,
    });

    const uploadRes = await uploadResponsePromise;
    expect(uploadRes.status()).toBe(200);

    // Preview should display the uploaded logo URL
    const previewImg = page.locator('[data-testid="logo-light-preview"]');
    await expect(previewImg).toBeVisible({ timeout: 5000 });
    const src = await previewImg.getAttribute("src");
    expect(src).toContain("/api/v1/branding/logo/light");

    expect(consoleErrors).toHaveLength(0);
  });

  test("11. Branding: upload an SVG -> 422 under the field", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings?k=branding", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    const fileInput = page.locator('[data-testid="logo-light-input"]');

    const svgBuffer = Buffer.from(
      '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="40" /></svg>',
    );

    const uploadResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings/branding/logo/light") &&
        res.request().method() === "POST",
    );

    await fileInput.setInputFiles({
      name: "invalid-logo.svg",
      mimeType: "image/svg+xml",
      buffer: svgBuffer,
    });

    const uploadRes = await uploadResponsePromise;
    expect(uploadRes.status()).toBe(422);

    // 422 translated error message must appear under the field
    const errorMsg = page.locator('[data-testid="logo-light-error"]');
    await expect(errorMsg).toBeVisible({ timeout: 5000 });
    const text = await errorMsg.textContent();
    expect(text.trim().length).toBeGreaterThan(0);

    expect(consoleErrors).toHaveLength(0);
  });

  test("12. Branding: save a receipt header line -> reload keeps it", async ({
    page,
  }) => {
    const consoleErrors = setupConsoleListener(page);

    await page.goto("/settings?k=branding", { waitUntil: "networkidle" });
    await dismissAnyModal(page);
    await page.waitForSelector("#app", { timeout: 15000 });

    const headerTextarea = page.locator("#receipt_header_input");
    await expect(headerTextarea).toBeVisible({ timeout: 10000 });

    const testHeader = "فرع المعادي - شارع النصر\nهاتف: 01012345678";
    await headerTextarea.fill(testHeader);
    await page.waitForTimeout(200);

    const saveBtn = page
      .locator(
        'button:has-text("حفظ التعديلات"), header button:has-text("حفظ")',
      )
      .first();

    const saveResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes("/api/v1/settings") &&
        res.request().method() === "POST",
    );

    await saveBtn.click();
    const saveRes = await saveResponsePromise;
    expect(saveRes.status()).toBe(200);

    // Wait for notification to disappear
    await page.waitForTimeout(1600);

    // Reload page to verify persisted value from API
    await page.goto("/settings?k=branding", { waitUntil: "networkidle" });
    await dismissAnyModal(page);

    const reloadedTextarea = page.locator("#receipt_header_input");
    await expect(reloadedTextarea).toHaveValue(testHeader, { timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});
