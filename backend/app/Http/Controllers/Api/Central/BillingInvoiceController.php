<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Billing\RecordManualPaymentAction;
use App\Actions\Billing\ShowBillingInvoiceAction;
use App\Actions\Billing\VoidSubscriptionInvoiceAction;
use App\DTOs\Billing\RecordManualPaymentDTO;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RecordBillingPaymentRequest;
use App\Http\Resources\Billing\BillingInvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One subscription invoice (console), routes/central.php:
 *   GET  /api/v1/super-admin/billing-invoices/{invoice}        super_admin.billing.view
 *   POST /api/v1/super-admin/billing-invoices/{invoice}/void   super_admin.billing.manage + step-up
 *   POST /api/v1/super-admin/billing-invoices/{invoice}/record-payment   super_admin.billing.manage + step-up (OPS-20)
 */
final class BillingInvoiceController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly ShowBillingInvoiceAction $showAction,
        private readonly VoidSubscriptionInvoiceAction $voidAction,
        private readonly RecordManualPaymentAction $recordPaymentAction,
    ) {}

    public function show(int $invoice): JsonResponse
    {
        return (new BillingInvoiceResource($this->showAction->execute($invoice)))
            ->additional(['success' => true])
            ->response();
    }

    public function void(Request $request, int $invoice): JsonResponse
    {
        $this->voidAction->execute($invoice, $this->operator($request));

        return (new BillingInvoiceResource($this->showAction->execute($invoice)))
            ->additional(['success' => true, 'message' => __('billing.messages.invoice_voided')])
            ->response();
    }

    public function recordPayment(RecordBillingPaymentRequest $request, int $invoice): JsonResponse
    {
        $this->recordPaymentAction->execute(RecordManualPaymentDTO::fromArray($request->validated(), $invoice), $this->operator($request));

        return (new BillingInvoiceResource($this->showAction->execute($invoice)))
            ->additional(['success' => true, 'message' => __('billing.messages.payment_recorded')])
            ->response();
    }
}
