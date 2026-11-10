<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landing;

use App\Actions\Landing\GetPublicLegalDocumentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * PRD-08: GET /api/v1/public/legal/{document} (`terms`, `privacy`, `dpa`).
 * Public, no authentication, no tenancy. Throttled by `public-api`.
 */
final class PublicLegalDocumentController extends Controller
{
    public function __construct(
        private readonly GetPublicLegalDocumentAction $getLegalDocument,
    ) {}

    public function show(string $document): JsonResponse
    {
        return response()->json([
            'data' => $this->getLegalDocument->execute($document, app()->getLocale()),
        ]);
    }
}
