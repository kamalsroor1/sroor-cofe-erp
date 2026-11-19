<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DataExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * PRD-17: one shop data export. The storage path is never exposed; a ready export carries a
 * short-lived RELATIVE signed download link (config data_export.link_minutes).
 *
 * @mixin DataExport
 */
class DataExportResource extends JsonResource
{
    public const DOWNLOAD_ROUTE = 'api.settings.data_export.download';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DataExport $export */
        $export = $this->resource;
        $downloadable = $export->isDownloadable();

        $linkExpires = null;
        $url = null;
        if ($downloadable && $export->expires_at !== null) {
            $linkExpires = now()->addMinutes(max(1, (int) config('data_export.link_minutes', 30)));
            if ($linkExpires->gt($export->expires_at)) {
                $linkExpires = $export->expires_at->copy();
            }
            $url = URL::temporarySignedRoute(self::DOWNLOAD_ROUTE, $linkExpires, ['dataExport' => $export->getKey()], false);
        }

        return [
            'id' => $export->id,
            'status' => $export->status->value,
            'file_size' => $export->file_size,
            'tables_count' => $export->tables_count,
            'rows_count' => $export->rows_count,
            'requested_at' => $export->created_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'is_downloadable' => $downloadable,
            'download_url' => $url,
            'download_url_expires_at' => $linkExpires?->toIso8601String(),
        ];
    }
}
