<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRD-06 commit payload (see CommitImportAction::execute for the shape).
 *
 * @property array{created: int, skipped: int, duplicates: int, errors: list<array<string, mixed>>} $resource
 */
final class ImportResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created' => $this->resource['created'],
            'skipped' => $this->resource['skipped'],
            'duplicates' => $this->resource['duplicates'],
            'errors' => $this->resource['errors'],
        ];
    }
}
