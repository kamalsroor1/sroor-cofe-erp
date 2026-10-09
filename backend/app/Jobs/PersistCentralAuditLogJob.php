<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CentralAuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Last-chance writer of a central audit row whose deferred write failed three times in a
 * row right after its transaction committed (CentralAuditLogger hardening, W2).
 *
 * Carries the row's raw, ALREADY REDACTED attributes (including the original created_at),
 * never a model: an unsaved model cannot be restored by SerializesModels. The row is
 * written through CentralAuditLog, which pins the CENTRAL connection whatever tenancy
 * context the worker restores. After the last try, failed() hands the row to
 * CentralAuditLogger::writeFailed(): critical log + report() + the AuditWriteFailed counter.
 */
final class PersistCentralAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $attributes  raw CentralAuditLog attributes (redacted, JSON-encoded properties)
     */
    public function __construct(
        public readonly array $attributes,
        public readonly string $writeId,
    ) {}

    /**
     * Seconds between tries: 10 s, 1 min, 5 min, 15 min.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(CentralAuditLogger $logger): void
    {
        $logger->persistQueued($this->attributes);
    }

    public function failed(?Throwable $exception): void
    {
        app(CentralAuditLogger::class)->writeFailed($this->attributes, $exception, $this->writeId);
    }
}
