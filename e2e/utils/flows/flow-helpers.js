import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { E2E_WORKSPACE_CODE } from "../e2e-user.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authStorageFile = path.resolve(__dirname, "../../.auth/user.json");

/**
 * Reads stored authentication details from the Playwright storageState file.
 * Throws loudly if the session file is missing instead of silently falling back.
 */
export function getStoredAuth() {
  if (!fs.existsSync(authStorageFile)) {
    throw new Error(
      `E2E storage state not found at ${authStorageFile}. Run the auth-setup project first (e2e/auth/login.setup.js).`,
    );
  }

  try {
    const data = JSON.parse(fs.readFileSync(authStorageFile, "utf8"));
    const origin = data.origins?.[0];
    const ls = origin?.localStorage || [];
    const getVal = (name) => ls.find((item) => item.name === name)?.value;

    const token = getVal("auth_token") || null;
    const tenant =
      getVal("tenant_id") ||
      getVal("active_tenant") ||
      E2E_WORKSPACE_CODE ||
      "demo";
    const storeId =
      getVal("current_store_id") || getVal("active_store_id") || "1";

    return { token, tenant, storeId };
  } catch (err) {
    throw new Error(
      `Failed to parse E2E storage state at ${authStorageFile}: ${err.message}`,
    );
  }
}

/**
 * Executes an authenticated API call with multi-tenancy & store headers.
 */
export async function apiCall(request, method, endpoint, options = {}) {
  const auth = getStoredAuth();
  const token = options.token !== undefined ? options.token : auth.token;
  const tenant = options.tenant !== undefined ? options.tenant : auth.tenant;
  const storeId =
    options.storeId !== undefined ? options.storeId : auth.storeId;

  const headers = {
    Accept: "application/json",
    ...(options.headers || {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (tenant) headers["X-Tenant"] = tenant;
  if (storeId) headers["X-Store-Id"] = String(storeId);
  if (options.data) headers["Content-Type"] = "application/json";

  const res = await request.fetch(endpoint, {
    method,
    headers,
    data: options.data,
  });

  let body = null;
  try {
    body = await res.json();
  } catch (_) {}

  return { status: res.status(), body, res };
}

/**
 * Formats a number to an exact 3-decimal string (half-up at 3 decimal places).
 */
export function toDecimal3(val) {
  if (val === null || val === undefined) return "0.000";
  const n = typeof val === "string" ? parseFloat(val) : Number(val);
  return Number.isFinite(n) ? n.toFixed(3) : "0.000";
}

/**
 * Exact 3-decimal addition helper.
 */
export function decAdd(a, b) {
  const n1 = parseFloat(a) || 0;
  const n2 = parseFloat(b) || 0;
  return (n1 + n2).toFixed(3);
}

/**
 * Exact 3-decimal subtraction helper.
 */
export function decSub(a, b) {
  const n1 = parseFloat(a) || 0;
  const n2 = parseFloat(b) || 0;
  return (n1 - n2).toFixed(3);
}

/**
 * Exact 3-decimal multiplication helper.
 */
export function decMul(a, b) {
  const n1 = parseFloat(a) || 0;
  const n2 = parseFloat(b) || 0;
  return (n1 * n2).toFixed(3);
}

/**
 * Collects runtime page errors while ignoring browser devtools noise.
 */
export function collectConsoleErrors(page) {
  const errors = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      const text = msg.text();
      if (
        !/favicon|ERR_CONNECTION_REFUSED|Vue Devtools|net::ERR_ABORTED/i.test(
          text,
        )
      ) {
        errors.push(text);
      }
    }
  });
  page.on("pageerror", (err) => errors.push(err.message));
  return errors;
}

/**
 * Intercepts window.print() inside pages and popup dialogs.
 */
export async function stubPrint(context) {
  await context.addInitScript(() => {
    window.__printCalls = 0;
    window.print = () => {
      window.__printCalls = (window.__printCalls || 0) + 1;
    };
  });
}

/**
 * Generates an obviously fake 11-digit phone number in a reserved test prefix range (0100009xxxx).
 */
export function generateFakePhone() {
  const suffix = Math.floor(1000 + Math.random() * 9000);
  return `0100009${suffix}`;
}

/**
 * Ensures an active shift exists for the store; opens one if none is active.
 */
export async function ensureShiftOpen(
  request,
  storeId = 1,
  openingCash = "500.000",
) {
  const current = await apiCall(request, "GET", "/api/v1/shifts/current", {
    storeId,
  });
  if (
    current.status === 200 &&
    current.body?.has_active &&
    current.body?.active_shift
  ) {
    return current.body.active_shift;
  }
  const opened = await apiCall(request, "POST", "/api/v1/shifts/open", {
    storeId,
    data: {
      opening_cash_balance: openingCash,
      notes: "E2E shift opened for business flow",
    },
  });
  return opened.body?.data;
}

/**
 * Creates a unique test item in the inventory and sets initial stock.
 */
export async function createTestItem(request, opts = {}) {
  const rand = Math.floor(Math.random() * 900000) + 100000;
  const name = opts.name || `صنف تجربة E2E ${rand}`;
  const code = opts.code || `ITM-E2E-${rand}`;
  const unit = opts.unit || (opts.is_weighted ? "كجم" : "قطعة");
  const cost = opts.cost || "50.000";
  const price = opts.price || "100.000";
  const is_weighted = Boolean(opts.is_weighted);
  const storeId = opts.storeId || 1;
  const stock = opts.stock !== undefined ? opts.stock : "20.000";

  const res = await apiCall(request, "POST", "/api/v1/items", {
    storeId,
    data: {
      name,
      code,
      unit,
      cost_price: cost,
      selling_price: price,
      min_selling_price: price,
      is_weighted,
      category: "عام",
      notes: "E2E test item",
    },
  });

  if (res.status !== 201) {
    throw new Error(`Failed to create test item: ${JSON.stringify(res.body)}`);
  }

  const item = res.body.data;

  // If initial stock requested, adjust stock
  if (parseFloat(stock) > 0) {
    await apiCall(request, "POST", `/api/v1/items/${item.id}/adjust-stock`, {
      storeId,
      data: {
        store_id: Number(storeId),
        movement_type: "stock_adjustment_in",
        quantity: String(stock),
        unit_cost: String(cost),
        notes: "E2E initial stock",
      },
    });
    // Fetch refreshed item
    const refreshed = await apiCall(
      request,
      "GET",
      `/api/v1/items/${item.id}`,
      { storeId },
    );
    return refreshed.body?.data || item;
  }

  return item;
}

/**
 * Creates a unique test customer with an obviously fake phone.
 */
export async function createTestCustomer(request, opts = {}) {
  const rand = Math.floor(Math.random() * 900000) + 100000;
  const name = opts.name || `عميل تجربة E2E ${rand}`;
  const phone = opts.phone || generateFakePhone();

  const res = await apiCall(request, "POST", "/api/v1/customers", {
    data: {
      name,
      phone,
      price_tier: opts.price_tier || "retail",
      notes: "E2E test customer",
    },
  });

  if (res.status !== 201) {
    throw new Error(
      `Failed to create test customer: ${JSON.stringify(res.body)}`,
    );
  }

  return res.body.data;
}

/**
 * Creates a unique test supplier with an obviously fake phone.
 */
export async function createTestSupplier(request, opts = {}) {
  const rand = Math.floor(Math.random() * 900000) + 100000;
  const name = opts.name || `مورد تجربة E2E ${rand}`;
  const phone = opts.phone || generateFakePhone();

  const res = await apiCall(request, "POST", "/api/v1/suppliers", {
    data: {
      name,
      phone,
      company_name: `شركة توريد تجربة ${rand}`,
      notes: "E2E test supplier",
    },
  });

  if (res.status !== 201) {
    throw new Error(
      `Failed to create test supplier: ${JSON.stringify(res.body)}`,
    );
  }

  return res.body.data;
}

/**
 * Creates a secondary branch/store.
 */
export async function createTestStore(request, opts = {}) {
  const rand = Math.floor(Math.random() * 9000) + 1000;
  const name = opts.name || `فرع تجربة E2E ${rand}`;
  const code = opts.code || `STR-E2E-${rand}`;

  const res = await apiCall(request, "POST", "/api/v1/stores", {
    data: {
      name,
      code,
      type: opts.type || "retail",
      is_main: false,
    },
  });

  if (res.status !== 201) {
    throw new Error(`Failed to create test store: ${JSON.stringify(res.body)}`);
  }

  return res.body.data;
}

/**
 * Creates a test user with a given role and obviously fake phone.
 */
export async function createTestUser(request, opts = {}) {
  const rand = Math.floor(Math.random() * 900000) + 100000;
  const name = opts.name || `موظف تجربة E2E ${rand}`;
  const phone = opts.phone || generateFakePhone();
  const role = opts.role || "cashier";
  const default_store_id = opts.default_store_id || 1;

  const res = await apiCall(request, "POST", "/api/v1/users", {
    data: {
      name,
      phone,
      password: "password",
      role,
      default_store_id,
      is_active: true,
    },
  });

  if (res.status !== 201) {
    throw new Error(`Failed to create test user: ${JSON.stringify(res.body)}`);
  }

  const user = res.body.data;

  // Log in as this user to get their Bearer token
  const loginRes = await apiCall(request, "POST", "/api/v1/auth/login", {
    token: null,
    data: {
      login: phone,
      password: "password",
    },
  });

  const token = loginRes.body?.data?.token;

  return { user, token, phone, password: "password" };
}

/**
 * Clean up test entities created during E2E runs.
 */
export async function cleanupTestRecords(request, records = {}) {
  if (Array.isArray(records.items)) {
    for (const item of records.items) {
      if (item?.id) {
        await apiCall(request, "DELETE", `/api/v1/items/${item.id}`).catch(
          () => {},
        );
      }
    }
  }
  if (Array.isArray(records.customers)) {
    for (const customer of records.customers) {
      if (customer?.id) {
        await apiCall(
          request,
          "DELETE",
          `/api/v1/customers/${customer.id}`,
        ).catch(() => {});
      }
    }
  }
  if (Array.isArray(records.suppliers)) {
    for (const supplier of records.suppliers) {
      if (supplier?.id) {
        await apiCall(
          request,
          "DELETE",
          `/api/v1/suppliers/${supplier.id}`,
        ).catch(() => {});
      }
    }
  }
  if (Array.isArray(records.users)) {
    for (const user of records.users) {
      if (user?.id) {
        await apiCall(request, "DELETE", `/api/v1/users/${user.id}`).catch(
          () => {},
        );
      }
    }
  }
}
