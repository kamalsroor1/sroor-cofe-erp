<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * How a tenant paid a SaaS invoice (`billing_payments.method`).
 *
 * Deliberately separate from App\Enums\PaymentMethod, which is the POS tender
 * enum inside each tenant DB.
 */
enum BillingPaymentMethod: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Instapay = 'instapay';
    case VodafoneCash = 'vodafone_cash';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case PaymobCard = 'paymob_card';
    case PaymobWallet = 'paymob_wallet';
    case FawryReference = 'fawry_reference';

    public static function translationGroup(): string
    {
        return 'billing_payment_method';
    }

    public function gateway(): BillingGateway
    {
        return match ($this) {
            self::Instapay, self::VodafoneCash, self::BankTransfer, self::Cash => BillingGateway::Manual,
            self::PaymobCard, self::PaymobWallet => BillingGateway::Paymob,
            self::FawryReference => BillingGateway::Fawry,
        };
    }

    /**
     * @return list<self>
     */
    public static function forGateway(BillingGateway $gateway): array
    {
        return array_values(array_filter(self::cases(), static fn (self $method): bool => $method->gateway() === $gateway));
    }
}
