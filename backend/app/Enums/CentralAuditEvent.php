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
