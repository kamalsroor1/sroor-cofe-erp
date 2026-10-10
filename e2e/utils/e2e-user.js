/**
 * Default E2E login: the admin of the local `demo` tenant.
 *
 * The old central "master key" login was removed, so the central admin phone (01000000001)
 * no longer opens a tenant session. scripts/local/setup-local.ps1 provisions `demo`
 * (through scripts/local/provision-local-tenant.php) with the admin phone 01000000201 and
 * the password you type during setup. Override any value through the environment:
 * E2E_USER_PHONE, E2E_USER_PASSWORD, E2E_WORKSPACE_CODE (docs/07-operations/local-testing.md).
 */
export const DEMO_TENANT_ADMIN_PHONE = '01000000201';
export const DEMO_TENANT_WORKSPACE_CODE = 'demo';

export const E2E_USER_PHONE = process.env.E2E_USER_PHONE || DEMO_TENANT_ADMIN_PHONE;
export const E2E_USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password';
export const E2E_WORKSPACE_CODE = process.env.E2E_WORKSPACE_CODE || DEMO_TENANT_WORKSPACE_CODE;
