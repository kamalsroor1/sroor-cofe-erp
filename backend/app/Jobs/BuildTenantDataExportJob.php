<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DataExport\BuildTenantDataExportAction;
use App\Enums\DataExportStatus;
use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * PRD-17: builds one shop data export. Dispatched from a tenant request, so stancl's
 * QueueTenancyBootstrapper re-initialises that tenant in the worker before handle() runs.
 * One attempt; a failure marks the row failed (the admin can ask again).
 */
final class BuildTenantDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $dataExportId)
    {
        $this->timeout = max(60, (int) config('data_export.job_timeout_seconds', 900));

        $connection = config('data_export.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $queue = config('data_export.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(BuildTenantDataExportAction $action): void
    {
        $export = DataExport::query()->find($this->dataExportId);

        if (! $export instanceof DataExport || $export->status !== DataExportStatus::Pending) {
            return;
        }

        $action->execute($export);
    }

    public function failed(?Throwable $e): void
    {
        $export = DataExport::query()->find($this->dataExportId);

        if ($export instanceof DataExport && in_array($export->status->value, DataExportStatus::activeValues(), true)) {
            $export->forceFill(['status' => DataExportStatus::Failed, 'error' => 'job_failed', 'completed_at' => now()])->save();
        }
    }
}
