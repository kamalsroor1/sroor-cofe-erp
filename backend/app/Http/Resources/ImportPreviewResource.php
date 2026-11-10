<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Import\ImportSessionStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-06 preview payload (see PreviewImportAction::execute for the shape).
 *
 * @property array{token: string, type: string, summary: array<string, int>, rows: list<array<string, mixed>>, rows_truncated: bool} $resource
 */
final class ImportPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'type' => $this->resource['type'],
            'summary' => $this->resource['summary'],
            'rows' => $this->resource['rows'],
            'rows_truncated' => $this->resource['rows_truncated'],
            'expires_in' => ImportSessionStore::TTL_SECONDS,
        ];
    }
}
