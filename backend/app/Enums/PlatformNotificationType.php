<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * #232 spec E / UI §4.2: kinds of super-admin panel notification items (central
 * `platform_notifications.type`). Each kind declares the permission an operator needs to see it.
 */
enum PlatformNotificationType: string
{
    case BillingReceiptUploaded = 'billing_receipt_uploaded';
    case BillingTemporaryAccessReminder = 'billing_temporary_access_reminder';

    public function permission(): CentralPermission
    {
        return match ($this) {
            self::BillingReceiptUploaded, self::BillingTemporaryAccessReminder => CentralPermission::BillingView,
        };
    }

    /**
     * @return list<string> the kinds an operator holding $permissions may list
     */
    public static function visibleWith(callable $can): array
    {
        $types = [];

        foreach (self::cases() as $case) {
            if ($can($case->permission()) === true) {
                $types[] = $case->value;
            }
        }

        return $types;
    }
}
