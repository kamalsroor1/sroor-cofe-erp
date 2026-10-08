<?php

declare(strict_types=1);

return [
    'populate_realistic_data' => [
        'refused_production' => 'Refusing to generate demo data in production. Re-run with --force-unsafe if you really mean it.',
        'confirm_production' => 'This will generate demo data in PRODUCTION. Continue?',
        'aborted' => 'Aborted: production run was not confirmed.',
        'existing_data' => 'Tenant already has operational data; re-run with --fresh to wipe it first.',
        'created_users' => 'Demo users were created:',
        'generated_password' => 'Generated password for the new demo users: :password',
        'password_from_option' => 'The new demo users use the password passed via --password.',
        'change_password_warning' => 'Change these credentials immediately after first login.',
    ],
    'seed' => [
        'generated_password' => 'Generated password for :user: :password , change it after first login.',
        'password_from_env' => 'The account :user uses the password provided via the environment.',
        'change_password_warning' => 'Change these credentials immediately after first login.',
        'tenant_exists' => 'Tenant :tenant already exists.',
        'tenant_provisioned' => 'Provisioned tenant: :tenant',
        'tenant_domains' => 'Domains: :domains',
    ],
    'audit_super_admin' => [
        'description' => 'Read-only audit of tenant databases for leaked super_admin roles, permissions and platform phones.',
        'tenant_not_found' => 'Tenant not found: :tenant',
        'no_tenants' => 'No tenants to audit.',
        'tenant_failed' => 'Could not audit tenant :tenant: :error',
        'col_tenant' => 'Tenant',
        'col_role_exists' => 'super_admin role',
        'col_role_users' => 'Users with super_admin',
        'col_permissions' => 'super_admin.* permissions',
        'col_permission_roles' => 'Roles holding them',
        'col_phone_matches' => 'Users matching platform phones',
        'findings' => 'Tenants with findings: :count of :total. Nothing was changed.',
    ],
];
