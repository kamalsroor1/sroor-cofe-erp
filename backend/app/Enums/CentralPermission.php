<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Granular platform-operator abilities (IDEN-1.2), granted to App\Models\CentralUser
 * through spatie roles on the `central` guard only (CentralPermissionsSeeder).
 *
 * Every value starts with `super_admin.`: AppServiceProvider's Gate::before refuses that
 * prefix to every tenant user (a store `admin` never inherits a platform ability) and
 * PermissionsSeeder excludes it from the tenant admin matrix.
 *
 * Derived from today's super-admin FormRequests / routes and the planned central routes:
 *  - tenants: StoreTenant, ToggleTenantStatus, UpdateTenantDatabaseConfig, OverrideTenantFeature,
 *    UpdateTenantUnits, run-migrations (manage); list/show (view); impersonation (IDEN-2.x);
 *  - plans: UpdatePlan (ENTI-2.8); billing invoices/payments (ENTI-3.x);
 *  - settings: UpdatePlatformSettings, UpdateSystemUnits, branding (BRND-2);
 *  - app versions / APK releases; audit log (IDEN-1.5); monitoring links (Telescope/Pulse/Horizon).
 *
 * Never rename a shipped value: roles reference it by name.
 */
enum CentralPermission: string
{
    case DashboardView = 'super_admin.dashboard.view';

    case TenantsView = 'super_admin.tenants.view';
    case TenantsManage = 'super_admin.tenants.manage';
    case TenantsImpersonate = 'super_admin.tenants.impersonate';

    case PlansView = 'super_admin.plans.view';
    case PlansManage = 'super_admin.plans.manage';

    case BillingView = 'super_admin.billing.view';
    case BillingManage = 'super_admin.billing.manage';

    case SettingsView = 'super_admin.settings.view';
    case SettingsManage = 'super_admin.settings.manage';

    case AppVersionsView = 'super_admin.app_versions.view';
    case AppVersionsManage = 'super_admin.app_versions.manage';

    case AuditView = 'super_admin.audit.view';

    case MonitoringView = 'super_admin.monitoring.view';

    public const GUARD = 'central';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SUPPORT = 'support';

    /**
     * Read-only abilities granted to the `support` role. Monitoring is excluded on purpose:
     * Telescope/Pulse expose raw cross-tenant requests and queries.
     */
    public function isReadOnly(): bool
    {
        return match ($this) {
            self::DashboardView,
            self::TenantsView,
            self::PlansView,
            self::BillingView,
            self::SettingsView,
            self::AppVersionsView,
            self::AuditView => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function readOnlyValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isReadOnly()),
        ));
    }

    /**
     * Permission names per central role.
     *
     * @return array<string, list<string>>
     */
    public static function roleMatrix(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => self::values(),
            self::ROLE_SUPPORT => self::readOnlyValues(),
        ];
    }
}
