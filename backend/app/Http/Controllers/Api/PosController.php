<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Customers\GetCustomerLastSoldPriceAction;
use App\Actions\Customers\QuickCreateCustomerAction;
use App\Actions\Invoices\GetInvoiceDetailsAction;
use App\Actions\Invoices\ProcessPOSInvoiceAction;
use App\Actions\POS\GetPOSBootstrapDataAction;
use App\DTOs\POSInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePOSInvoiceRequest;
use App\Http\Requests\StoreQuickCustomerRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PosController extends Controller
{
    public function __construct(
        private readonly GetPOSBootstrapDataAction $getPOSBootstrapDataAction,
        private readonly ProcessPOSInvoiceAction $processPOSInvoiceAction,
        private readonly QuickCreateCustomerAction $quickCreateCustomerAction,
        private readonly GetCustomerLastSoldPriceAction $getCustomerLastSoldPriceAction,
        private readonly GetInvoiceDetailsAction $getInvoiceDetailsAction
    ) {}

    /**
     * Bootstrap fast POS data (categories, items with stock, customers, active shift)
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('pos.access') && !$user->can('invoices.create')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $data = $this->getPOSBootstrapDataAction->execute($user);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Submit and confirm POS checkout atomically
     */
    public function checkout(StorePOSInvoiceRequest $request): JsonResponse
    {
        $dto = POSInvoiceDTO::fromArray($request->validated());
        $invoice = $this->processPOSInvoiceAction->execute($dto);

        $details = $this->getInvoiceDetailsAction->execute($invoice->id);

        return response()->json([
            'success'  => true,
            'message'  => __('pos.invoice_saved_success') ?: "تم حفظ واعتماد الفاتورة رقم: {$invoice->invoice_number} بنجاح ✓",
            'data'     => (new InvoiceResource($details['invoice']))->resolve(),
            'whatsapp' => $details['whatsapp'],
        ], 201);
    }

    /**
     * Fast Quick Customer Registration from POS
     */
    public function quickCustomer(StoreQuickCustomerRequest $request): JsonResponse
    {
        $customer = $this->quickCreateCustomerAction->execute($request->validated());

        return response()->json([
            'success'  => true,
            'message'  => __('pos.customer_registered_success') ?: 'تم تسجيل العميل بنجاح',
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'price_tier'      => $customer->price_tier ?? 'retail',
                'current_balance' => 0,
            ],
        ], 201);
    }

    /**
     * Get Last Sold Price for customer on specific item
     */
    public function lastPrice(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('pos.access') && !$user->can('invoices.create')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $customerId = (int)$request->query('customer_id');
        $itemId = (int)$request->query('item_id');
        $storeId = $request->header('X-Store-Id')
            ?: $request->query('store_id')
            ?: $user?->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $lastPrice = $this->getCustomerLastSoldPriceAction->execute(
            customerId: $customerId,
            itemId: $itemId,
            storeId: $storeId ? (int)$storeId : null
        );

        return response()->json([
            'success'    => true,
            'last_price' => $lastPrice,
        ], 200);
    }
}
