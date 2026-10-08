<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CASHIER = 'cashier';
    case STOREKEEPER = 'storekeeper';
    case ACCOUNTANT = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'مدير عام (كامل الصلاحيات)',
            self::CASHIER => 'كاشير مبيعات (POS وفواتير)',
            self::STOREKEEPER => 'أمين مخزن (أصناف وتوريدات)',
            self::ACCOUNTANT => 'محاسب مالي (تقارير وكشوفات)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ADMIN => '👑',
            self::CASHIER => '☕',
            self::STOREKEEPER => '📦',
            self::ACCOUNTANT => '📊',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ADMIN => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            self::CASHIER => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            self::STOREKEEPER => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::ACCOUNTANT => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
        };
    }

    public function formattedName(): string
    {
        return "{$this->icon()} {$this->label()}";
    }

    /**
     * Get the formatted display title for any role string (handles system enums & custom dynamic roles)
     */
    public static function getFormatted(string $roleName): string
    {
        $role = self::tryFrom($roleName);

        return $role ? $role->formattedName() : "🛡️ دور: {$roleName}";
    }

    /**
     * Get badge styling class for any role
     */
    public static function getBadgeClass(string $roleName): string
    {
        $role = self::tryFrom($roleName);

        return $role ? $role->badgeClass() : 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20';
    }
}
