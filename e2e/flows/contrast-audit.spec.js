// COLOR-01: WCAG 2.1 AA contrast audit. Walks the main tenant screens in light and dark and fails when any
// visible text is below 4.5:1 (3:1 for large text), measured from the real computed colours, not from class names.
// The maths lives in backend/resources/js/helpers/contrast.js (unit-tested in backend/tests/js/contrast.test.js).
import { test, expect } from "@playwright/test";
import {
  auditPage,
  groupFailures,
  AUDIT_ROUTES,
} from "../utils/contrast-audit.js";

test.use({ viewport: { width: 1366, height: 768 } });

// Elements that are exempt on purpose. Keep this short; every entry needs a reason a reviewer can check.
// `route` is the audit route name (or "*"), `path` is matched against the element path reported by the audit.
const ALLOW_LIST = [
  // Add { route, path, reason } entries only for decorative or inactive text (WCAG 1.4.3 exempts both).
];

const isAllowed = (routeName, group) =>
  ALLOW_LIST.some(
    (entry) =>
      (entry.route === "*" || entry.route === routeName) &&
      group.path.includes(entry.path),
  );

for (const mode of ["light", "dark"]) {
  test.describe(`contrast (${mode})`, () => {
    test.beforeEach(async ({ page }) => {
      await page.addInitScript((theme) => {
        localStorage.setItem("theme_preference", theme);
      }, mode);
    });

    for (const route of AUDIT_ROUTES) {
      test(`${route.name} has no text below AA`, async ({ page }) => {
        await page.goto(route.path);
        await page.waitForLoadState("networkidle");
        await expect(
          page.locator(
            'div.animate-pulse[class*="bg-slate-200"], [class*="shimmer"], [class*="skeleton"]',
          ),
        ).toHaveCount(0);

        const result = await auditPage(page, mode);
        const offenders = groupFailures(result.failing).filter(
          (group) => !isAllowed(route.name, group),
        );

        expect(
          offenders,
          `${route.name} (${mode}): ${result.failing.length}/${result.total} text nodes below AA\n` +
            offenders
              .map((g) => `  ${g.count}x ${g.worst}:1 "${g.sample}" ${g.path}`)
              .join("\n"),
        ).toEqual([]);
      });
    }
  });
}
