<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Server-rendered (Blade) invoice print pages for the tenant web routes.
 * Guarded by `auth` + `can:invoices.view` at the route level; this controller
 * additionally enforces the invoice policy and branch (store) access.
 */
final class InvoicePrintController extends Controller
{
    public function thermal(Request $request, int $id): View
    {
        return view('layouts.print-thermal', ['invoice' => $this->loadAuthorizedInvoice($request, $id)]);
    }

    public function a4(Request $request, int $id): View
    {
        return view('layouts.print-a4', ['invoice' => $this->loadAuthorizedInvoice($request, $id)]);
    }

    private function loadAuthorizedInvoice(Request $request, int $id): Invoice
    {
        $invoice = Invoice::with(['customer', 'items.item', 'additionalExpenses'])->findOrFail($id);

        Gate::authorize('view', $invoice);

        /** @var User $user */
        $user = $request->user();
        $this->assertStoreAccess($user, (int) $invoice->store_id);

        return $invoice;
    }

    private function assertStoreAccess(User $user, int $storeId): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->stores()->whereKey($storeId)->exists()) {
            return;
        }

        if ((int) $user->default_store_id === $storeId) {
            return;
        }

        abort(403, __('common.store_access_denied'));
    }
}
