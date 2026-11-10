<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Imports\CommitImportAction;
use App\Actions\Imports\DownloadImportTemplateAction;
use App\Actions\Imports\PreviewImportAction;
use App\DTOs\Imports\CommitImportDTO;
use App\DTOs\Imports\PreviewImportDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommitImportRequest;
use App\Http\Requests\ImportTemplateRequest;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Resources\ImportPreviewResource;
use App\Http\Resources\ImportResultResource;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * PRD-06: Excel/CSV import wizard (items, customers, suppliers, categories).
 */
final class ImportController extends Controller
{
    public function __construct(
        private readonly DownloadImportTemplateAction $downloadTemplate,
        private readonly PreviewImportAction $previewImport,
        private readonly CommitImportAction $commitImport,
        private readonly ActiveStore $activeStore,
    ) {}

    public function template(ImportTemplateRequest $request): BinaryFileResponse
    {
        return $this->downloadTemplate->execute($request->importType());
    }

    public function preview(PreviewImportRequest $request): JsonResponse
    {
        $result = $this->previewImport->execute(PreviewImportDTO::fromArray([
            'type' => $request->importType(),
            'file' => $request->file('file'),
            'user_id' => (int) $request->user()?->id,
        ]));

        return response()->json([
            'success' => true,
            'data' => (new ImportPreviewResource($result))->resolve(),
        ]);
    }

    public function commit(CommitImportRequest $request): JsonResponse
    {
        $result = $this->commitImport->execute(CommitImportDTO::fromArray([
            ...$request->validated(),
            'type' => $request->importType(),
            'user_id' => (int) $request->user()?->id,
            'store_id' => $this->activeStore->id(),
        ]));

        return response()->json([
            'success' => true,
            'message' => __('import.committed', ['count' => $result['created']]),
            'data' => (new ImportResultResource($result))->resolve(),
        ]);
    }
}
