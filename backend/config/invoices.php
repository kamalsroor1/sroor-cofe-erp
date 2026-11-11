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
];
