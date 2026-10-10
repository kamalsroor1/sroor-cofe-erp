<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Events recorded in the central (platform-operator) audit log, IDEN-1.5.
 *
 * Stored as the string value in `central_audit_logs.event` (max 64 chars). Later tasks
 * APPEND cases here (impersonation IDEN-2.x, lifecycle IDEN-3.x, billing ENTI-3.x) and
 * add the matching label to lang/{ar,en}/central_audit.php → `events.<value>`.
 * Never rename or remove a shipped value: old rows keep it forever.
 */
enum CentralAuditEvent: string
{
    // Operator authentication (IDEN-1.3 / 1.12 / 1.13)
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case PasswordChanged = 'password_changed';

    // Operator accounts (IDEN-1.14)
    case CentralUserCreated = 'central_user_created';
    case CentralUserUpdated = 'central_user_updated';
    case CentralUserDeactivated = 'central_user_deactivated';
    case CentralUserDeleted = 'central_user_deleted';

    // Impersonation (IDEN-2.6 … 2.10)
    case ImpersonationStarted = 'impersonation_started';
    case ImpersonationExchanged = 'impersonation_exchanged';
    case ImpersonationEnded = 'impersonation_ended';
    case ImpersonationDestructiveUsed = 'impersonation_destructive_used';

    // Tenant lifecycle (IDEN-3.x)
    case TenantCreated = 'tenant_created';
    case TenantUpdated = 'tenant_updated';
    case TenantSuspended = 'tenant_suspended';
    case TenantReactivated = 'tenant_reactivated';
    case TenantTrialExtended = 'tenant_trial_extended';
    case TenantDeleted = 'tenant_deleted';

    // Billing / plans (ENTI-3.x)
    case SubscriptionActivated = 'subscription_activated';
    case PlanUpdated = 'plan_updated';

    // W2 (identity, lifecycle, provisioning, platform settings, backups, entitlements)
    case TwoFactorConfirmed = 'two_factor_confirmed';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case RecoveryCodeUsed = 'recovery_code_used';
    case StepUpConfirmed = 'step_up_confirmed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case SuperAdminMigrated = 'super_admin_migrated';
    case TenantStatusChanged = 'tenant_status_changed';
    case TenantArchived = 'tenant_archived';
    case TenantUnarchived = 'tenant_unarchived';
    case TenantArchiveRefused = 'tenant_archive_refused';
    case TenantPurged = 'tenant_purged';
    case TenantPurgeRefused = 'tenant_purge_refused';
    case TenantDbConfigUpdated = 'tenant_db_config_updated';
    case TenantProvisioningStarted = 'tenant_provisioning_started';
    case TenantProvisioningSucceeded = 'tenant_provisioning_succeeded';
    case TenantProvisioningFailed = 'tenant_provisioning_failed';
    case TenantRateLimitRaised = 'tenant_rate_limit_raised';
    case PlatformSettingsUpdated = 'platform_settings_updated';
    case PlatformAssetUploaded = 'platform_asset_uploaded';
    case PlatformAssetDeleted = 'platform_asset_deleted';
    case BackupSucceeded = 'backup_succeeded';
    case BackupFailed = 'backup_failed';
    case AuditWriteFailed = 'audit_write_failed';
    case EntitlementsOverridden = 'entitlements_overridden';

    // W2 batch 2 security: an operator read their 2FA recovery codes
    case RecoveryCodesViewed = 'recovery_codes_viewed';

    // W2 batch 3 (lane 3G): monitoring access and the legacy control-plane endpoints
    case TelescopeLinkIssued = 'telescope_link_issued';
    case TenantMigrationsRun = 'tenant_migrations_run';
    case TenantFeatureOverridden = 'tenant_feature_overridden';
    case TenantUnitsUpdated = 'tenant_units_updated';
    case PlatformUnitsUpdated = 'platform_units_updated';
    case AppVersionCreated = 'app_version_created';
    case AppVersionToggled = 'app_version_toggled';
    case AppVersionDeleted = 'app_version_deleted';

    public function translationKey(): string
    {
        return 'central_audit.events.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
