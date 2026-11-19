<?php

declare(strict_types=1);

namespace App\Actions\DataExport;

use App\Enums\DataExportStatus;
use App\Jobs\BuildTenantDataExportJob;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * PRD-17: an admin asks for a full export of the CURRENT shop's data.
 *
 * One export at a time per shop: while one is pending/processing (and not stale) a new
 * request is a 422. Older ZIPs are removed from the disk (a shop keeps at most one archive),
 * then the builder job is queued. The job carries the tenant (QueueTenancyBootstrapper).
 */
final class RequestDataExportAction
{
    public function execute(User $requester): DataExport
    {
        $export = DB::transaction(function () use ($requester): DataExport {
            $active = DataExport::query()
                ->whereIn('status', DataExportStatus::activeValues())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($active as $row) {
                if (! $row->isStale()) {
                    throw ValidationException::withMessages([
                        'export' => [__('settings.data_export.already_running')],
                    ]);
                }

                $row->forceFill([
                    'status' => DataExportStatus::Failed,
                    'error' => 'stale',
                    'completed_at' => now(),
                ])->save();
            }

            return DataExport::query()->create([
                'user_id' => $requester->getKey(),
                'status' => DataExportStatus::Pending,
            ]);
        });

        $this->removePreviousArchives($export);

        BuildTenantDataExportJob::dispatch((int) $export->getKey());

        return $export->refresh();
    }

    private function removePreviousArchives(DataExport $current): void
    {
        $disk = Storage::disk((string) config('data_export.disk', 'local'));

        DataExport::query()
            ->whereKeyNot($current->getKey())
            ->whereNotNull('file_path')
            ->each(function (DataExport $old) use ($disk): void {
                $disk->delete((string) $old->file_path);
                $old->forceFill([
                    'status' => $old->status === DataExportStatus::Ready ? DataExportStatus::Expired : $old->status,
                    'file_path' => null,
                ])->save();
            });
    }
}
