import { test } from "@playwright/test";
import { auditPageUx } from "../utils/ux-audit-helper.js";

test.describe("Trash UX Audit", () => {
  test.use({ storageState: "e2e/.auth/user.json" });

  test("verifies trash page responsive layout, RTL, dark/light, and error states", async ({
    page,
  }) => {
    await auditPageUx(page, {
      route: "/trash",
      testId: "trash-table",
      apiPattern: "**/api/trash*",
    });
  });
});
