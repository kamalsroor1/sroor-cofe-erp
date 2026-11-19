<?php

declare(strict_types=1);

namespace App\Actions\DataExport;

use App\Models\DataExport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PRD-17: stream a ready, unexpired export of the CURRENT shop. The id comes from a signed
 * link and is looked up in the tenant DB only, so another shop's export can never resolve.
 * Missing / not ready = 404, expired = 410.
 */
final class ResolveDataExportDownloadAction
{
    public function execute(int $id): StreamedResponse
    {
        $export = DataExport::query()->findOrFail($id);

        abort_if($export->file_path === null, 404);
        abort_unless($export->isDownloadable(), 410, (string) __('settings.data_export.expired'));

        $disk = Storage::disk((string) config('data_export.disk', 'local'));
        abort_unless($disk->exists($export->file_path), 404);

        $name = 'shop-data-'.($export->completed_at ?? now())->format('Y-m-d-His').'.zip';

        return $disk->download($export->file_path, $name, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
