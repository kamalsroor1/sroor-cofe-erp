import { test } from "@playwright/test";
import { auditPageUx } from "../utils/ux-audit-helper.js";

test.describe("Stores UX Audit", () => {
  test.use({ storageState: "e2e/.auth/user.json" });

  test("verifies stores page responsive layout, RTL, dark/light, and error states", async ({
    page,
  }) => {
    await auditPageUx(page, {
      route: "/stores",
      apiPattern: "**/api/stores*",
    });
  });
});
