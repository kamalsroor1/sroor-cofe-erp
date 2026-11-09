<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Returns\ListReturnSourcesAction;
use App\Actions\Returns\ShowReturnSourceAction;
use App\Http\Controllers\Controller;
use App\Models\ReturnDocument;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * BE-01: the invoices/purchases (and their returnable lines) the create-return screen picks from.
 */
final class ReturnSourceController extends Controller
{
    private const TYPES = ['sales_return', 'purchase_return'];

    public function __construct(
        private readonly ListReturnSourcesAction $listAction,
        private readonly ShowReturnSourceAction $showAction,
    ) {}

    public function index(Request $request, ActiveStore $activeStore, string $type): JsonResponse
    {
        $this->authorizeType($type);
        $page = $this->listAction->execute(
            $type,
            (int) $activeStore->requireStore()->id,
            trim((string) $request->query('search', '')),
            (int) $request->query('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(ActiveStore $activeStore, string $type, int $id): JsonResponse
    {
        $this->authorizeType($type);

        return response()->json([
            'success' => true,
            'data' => $this->showAction->execute($type, $id, (int) $activeStore->requireStore()->id),
        ]);
    }

    private function authorizeType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        Gate::authorize('create', ReturnDocument::class);
    }
}
