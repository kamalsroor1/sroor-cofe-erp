<?php

declare(strict_types=1);

/*
| PRD-17: the shop's self-service "Export my data" (Settings). An admin asks for a ZIP of CSVs
| of every main entity; a queued job builds it on the tenant's private disk and the SPA
| downloads it through a short-lived signed link.
*/
return [
    // Private, tenant-suffixed disk (FilesystemTenancyBootstrapper) the ZIP is written to.
    'disk' => env('DATA_EXPORT_DISK', 'local'),

    // Folder on that disk. File names are generated server-side (uuid).
    'directory' => 'data-exports',

    // Lifetime of the signed download link handed to the SPA.
    'link_minutes' => (int) env('DATA_EXPORT_LINK_MINUTES', 30),

    // How long a ready ZIP stays downloadable; a new request also removes older files.
    'retention_hours' => (int) env('DATA_EXPORT_RETENTION_HOURS', 24),

    // A pending/processing export older than this is treated as dead (worker crash): a new one may start.
    'stale_minutes' => (int) env('DATA_EXPORT_STALE_MINUTES', 60),

    // Rows read per query while streaming a table into its CSV.
    'chunk_size' => (int) env('DATA_EXPORT_CHUNK_SIZE', 500),

    // Queue connection / name for the builder job (empty = default).
    'queue_connection' => env('DATA_EXPORT_QUEUE_CONNECTION'),
    'queue' => env('DATA_EXPORT_QUEUE'),
    'job_timeout_seconds' => (int) env('DATA_EXPORT_JOB_TIMEOUT', 900),
];
