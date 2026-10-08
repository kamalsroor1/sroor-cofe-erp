<?php

return [
    'subscription_status' => [
        'trialing' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Past due',
        'pending_payment' => 'Pending payment',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],
    'subscription_addon_status' => [
        'active' => 'Active',
        'pending_payment' => 'Pending payment',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],
    'billing_cycle' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'biennial' => 'Every two years',
    ],
    'addon_type' => [
        'recurring' => 'Recurring add-on',
        'service' => 'Service',
    ],
    'billing_invoice_status' => [
        'draft' => 'Draft',
        'pending' => 'Awaiting payment',
        'paid' => 'Paid',
        'void' => 'Void',
        'refunded' => 'Refunded',
    ],
    'billing_invoice_type' => [
        'plan' => 'New subscription',
        'renewal' => 'Renewal',
        'upgrade' => 'Plan upgrade',
        'addon' => 'Add-on',
        'service' => 'Service',
    ],
    'billing_payment_status' => [
        'pending' => 'Under review',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],
    'billing_payment_method' => [
        'instapay' => 'InstaPay',
        'vodafone_cash' => 'Vodafone Cash',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'paymob_card' => 'Card (Paymob)',
        'paymob_wallet' => 'Mobile wallet (Paymob)',
        'fawry_reference' => 'Fawry reference code',
    ],
    'billing_gateway' => [
        'manual' => 'Manual review',
        'paymob' => 'Paymob',
        'fawry' => 'Fawry',
    ],
    'addon_pricing' => [
        'cycle_not_sellable' => 'The :cycle billing cycle is not available for purchase.',
        'yearly_price_missing' => 'No yearly price is set for add-on :addon.',
        'invalid_quantity' => 'Invalid add-on quantity (:quantity). It must be at least 1.',
        'invalid_price_tiers' => 'The volume price tiers of add-on :addon are invalid.',
        'invalid_price' => 'The stored price of add-on :addon is invalid.',
    ],
    'sequence' => [
        'transaction_required' => 'A billing number can only be issued inside a database transaction.',
        'invalid_key' => 'Invalid billing number sequence.',
        'invalid_configuration' => 'The billing numbering setting :key is invalid.',
    ],
    'founder_pricing' => [
        'transaction_required' => 'A founder pricing slot can only be claimed inside a database transaction.',
        'payment_not_verified' => 'A founder pricing slot can only be claimed for a verified payment.',
        'payment_tenant_mismatch' => 'The payment does not belong to the subscription account.',
        'cycle_not_sellable' => 'The :cycle billing cycle is not available for purchase.',
        'invalid_configuration' => 'The founder pricing setting :key is invalid.',
    ],
    'subscription_addon' => [
        'subscription_missing' => 'An add-on line cannot be saved without an existing subscription.',
        'tenant_mismatch' => 'The add-on line does not belong to the account of its subscription.',
    ],
];
