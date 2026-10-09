import { test } from "@playwright/test";
import { auditPageUx } from "../utils/ux-audit-helper.js";

test.describe("Expenses UX Audit", () => {
  test.use({ storageState: "e2e/.auth/user.json" });

  test("verifies expenses page responsive layout, RTL, dark/light, and error states", async ({
    page,
  }) => {
    await auditPageUx(page, {
      route: "/expenses",
      testId: "expenses-table",
      apiPattern: "**/api/expenses*",
    });
  });
});
