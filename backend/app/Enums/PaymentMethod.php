<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Instapay = 'instapay';
    case EWallet = 'e_wallet';
    case Visa = 'visa';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقداً (كاش)',
            self::Instapay => 'إنستاباي (InstaPay)',
            self::EWallet => 'محفظة إلكترونية (فودافون/أورانج/اتصالات)',
            self::Visa => 'بطاقة بنكية / فيزا',
            self::BankTransfer => 'تحويل بنكي',
            self::Check => 'شيك بنكي',
            self::Other => 'أخرى',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::Instapay => 'إنستاباي',
            self::EWallet => 'محفظة',
            self::Visa => 'فيزا',
            self::BankTransfer => 'تحويل',
            self::Check => 'شيك',
            self::Other => 'أخرى',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => '💵',
            self::Instapay => '⚡',
            self::EWallet => '📲',
            self::Visa => '💳',
            self::BankTransfer => '🏦',
            self::Check => '📝',
            self::Other => '🔄',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Cash => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            self::Instapay => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
            self::EWallet => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            self::Visa => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
            self::BankTransfer => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
            self::Check => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::Other => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
        };
    }

    /**
     * Determine if payment method is currently active for selection
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Cash, self::Instapay, self::EWallet => true,
            default => false,
        };
    }

    /**
     * Active payment methods in the system (Cash, InstaPay, E-Wallet)
     */
    public static function activeMethods(): array
    {
        return [
            self::Cash,
            self::Instapay,
            self::EWallet,
        ];
    }

    /**
     * Methods available in POS / Sales Invoice
     */
    public static function posMethods(): array
    {
        return self::activeMethods();
    }

    /**
     * Methods that deposit physical cash into drawer
     */
    public function isPhysicalCash(): bool
    {
        return $this === self::Cash;
    }
}
