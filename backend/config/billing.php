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
    | Days an issued subscription invoice stays payable before it is overdue (PRD-03).
    */
    'invoice_due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),

    /*
    | SELL-06: `billing:issue-trial-invoices` (daily) issues a trial tenant's first plan invoice
    | this many days before the trial ends (owner decision: 7). Whole days, at least 0.
    */
    'trial_invoice_lead_days' => max(0, (int) env('BILLING_TRIAL_INVOICE_LEAD_DAYS', 7)),

    /*
    | Payment receipt uploads (ENTI-3.3): jpg/png/webp/pdf only (never SVG), size in kilobytes.
    */
    'receipt' => [
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    /*
    | Lifetime of the signed receipt link handed to a super-admin (minutes).
    */
    'receipt_url_minutes' => 5,

    /*
    | Founder pricing (ENTI-1.7, CTO decision): the first `slots` paying customers keep the
    | plan founder price (plans.founder_price_*) for `months` months from their first payment.
    | Plain values, not env: changing them is a product decision. Lowering `slots` below the
    | slots already handed out never takes a slot back.
    |
    | Price schedule of a founder subscription (owner decision 2026-11-15), measured from the
    | tenant's first verified payment:
    |   - `months` (12): the year-1 founder price (plans.founder_price_*); its end is stored
    |     per subscription in subscriptions.founder_price_until;
    |   - `transition_months` (12) after that: the transition price
    |     (plans.founder_transition_price_*); its end is DERIVED as founder_price_until +
    |     transition_months, never stored, so changing this value moves the end for every
    |     founder (a product decision, like `months`);
    |   - then the plan's list price.
    | `notice_days` (30): the tenant billing screen flags the next step this many days ahead.
    */
    'founder' => [
        'slots' => 50,
        'months' => 12,
        'transition_months' => 12,
        'notice_days' => 30,
    ],

];
