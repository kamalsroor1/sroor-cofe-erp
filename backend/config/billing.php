<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SaaS billing (central)
|--------------------------------------------------------------------------
|
| Platform billing of tenants (subscription invoices and their payments). This
| is NOT the POS invoicing inside each tenant DB.
|
| Never put wallet numbers, bank accounts or gateway secrets here as literals:
| everything that identifies the operator comes from the environment.
|
*/

return [

    /*
    | Timezone used to decide which period (year) an invoice number belongs to.
    */
    'timezone' => env('BILLING_TIMEZONE', 'Africa/Cairo'),

    /*
    | SaaS invoice numbers: <prefix>-<period>-<zero-padded counter>, e.g. SUB-2026-00001.
    |
    | - prefix: letters/digits only, up to 12 characters, may be empty. It is a neutral
    |   config value and must never be derived from the platform/brand name.
    | - period: `yearly` (the counter restarts every year) or `none` (one continuous counter).
    | - pad: minimum digits of the counter (1-12); larger numbers are never truncated.
    |
    | Defaults = CTO decision W1 Q4 [2026-10-09]: SUB-YYYY-00001, reset every year
    | (billing_sequences period = the year in billing.timezone). Changing the scheme later
    | never renumbers existing invoices, but mixing schemes in one year should be avoided.
    */
    'invoice_number' => [
        'prefix' => env('BILLING_INVOICE_PREFIX', 'SUB'),
        'period' => env('BILLING_INVOICE_NUMBER_PERIOD', 'yearly'),
        'pad' => (int) env('BILLING_INVOICE_NUMBER_PAD', 5),
    ],

    /*
    | Founder pricing (ENTI-1.7, CTO decision): the first `slots` paying customers keep the
    | plan founder price (plans.founder_price_*) for `months` months from their first payment.
    | Plain values, not env: changing them is a product decision. Lowering `slots` below the
    | slots already handed out never takes a slot back.
    */
    'founder' => [
        'slots' => 50,
        'months' => 12,
    ],

];
