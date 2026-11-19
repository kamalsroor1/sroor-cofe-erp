<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Invoices Configuration
    |--------------------------------------------------------------------------
    |
    | max_export_rows: Maximum number of rows allowed in a single CSV export.
    | Prevents memory bloat and request timeouts on large datasets.
    |
    */
    'max_export_rows' => (int) env('INVOICES_MAX_EXPORT_ROWS', 20000),

    /*
    | PRD-22: public receipt links shared with the customer (WhatsApp). A signed URL on the shop's
    | own host, valid this many days; the shop can always send a fresh link.
    */
    'receipt_links' => [
        'ttl_days' => max(1, (int) env('RECEIPT_LINK_TTL_DAYS', 30)),
    ],
];
