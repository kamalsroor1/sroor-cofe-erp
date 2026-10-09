<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\ExportService;
use App\Support\ClientStoreGuard;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function exportCustomerStatement($id, ExportService $exportService)
    {
        $customer = Customer::findOrFail($id);

        return $exportService->exportCustomerStatement($customer);
    }

    public function exportSupplierStatement($id, ExportService $exportService)
    {
        $supplier = Supplier::findOrFail($id);

        return $exportService->exportSupplierStatement($supplier);
    }

    public function exportInventory(ExportService $exportService)
    {
        return $exportService->exportInventory();
    }

    public function exportItemMovements($id, Request $request, ExportService $exportService)
    {
        $item = Item::withTrashed()->findOrFail($id);
        $clientStoreId = ClientStoreGuard::verified($request, 'store_id', 'query');
        $storeId = is_int($clientStoreId) ? $clientStoreId : null;
        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        $filterType = $request->query('type');

        return $exportService->exportItemMovements($item, $fromDate, $toDate, $storeId, $filterType);
    }
}
