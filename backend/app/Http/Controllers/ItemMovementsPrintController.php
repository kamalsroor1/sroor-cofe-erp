<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Items\GetItemMovementsPrintDataAction;
use App\Models\Item;
use App\Support\ReportStoreFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Item movements A4 print page (Blade). Guarded by `auth` + `can:items.view` + `store.access`
 * at the route; the store filter is access-checked on the exact value used.
 */
final class ItemMovementsPrintController extends Controller
{
    public function __construct(private readonly GetItemMovementsPrintDataAction $getPrintData) {}

    public function __invoke(Request $request, int $id): View
    {
        $item = Item::withTrashed()->findOrFail($id);
        $storeId = ReportStoreFilter::resolve($request, $request->user());

        return view('layouts.print-item-movements-a4', $this->getPrintData->execute(
            $item,
            $storeId,
            $request->query('from') ? (string) $request->query('from') : null,
            $request->query('to') ? (string) $request->query('to') : null,
            $request->query('type') ? (string) $request->query('type') : null,
        ));
    }
}
