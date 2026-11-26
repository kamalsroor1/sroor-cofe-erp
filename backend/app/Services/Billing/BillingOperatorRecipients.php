<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use LogicException;

/**
 * #232 spec E: the e-mail addresses of the operators who get billing e-mails.
 * config('billing.admin_notifications.emails') (env BILLING_ADMIN_NOTIFICATION_EMAILS) when set;
 * otherwise every ACTIVE central user holding super_admin.billing.manage.
 * Central context only (spatie's permission tables are central).
 */
final class BillingOperatorRecipients
{
    /**
     * @return list<string> unique, lower-cased, valid addresses
     */
    public function emails(): array
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new LogicException('BillingOperatorRecipients must run in central context.');
        }

        $configured = config('billing.admin_notifications.emails', []);
        $emails = is_array($configured) ? $configured : [];

        if ($emails === []) {
            foreach (CentralUser::query()->where('is_active', true)->orderBy('id')->get() as $user) {
                if ($user->checkPermissionTo(CentralPermission::BillingManage->value, CentralPermission::GUARD)) {
                    $emails[] = $user->email;
                }
            }
        }

        $valid = array_filter(
            array_map(static fn (mixed $email): string => is_string($email) ? strtolower(trim($email)) : '', $emails),
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        );

        return array_values(array_unique($valid));
    }
}
