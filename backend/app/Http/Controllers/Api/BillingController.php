<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelOwnBillingPaymentAction;
use App\Actions\Billing\GetTenantSubscriptionAction;
use App\Actions\Billing\ListOwnBillingInvoicesAction;
use App\Actions\Billing\RequestRenewalInvoiceAction;
use App\Actions\Billing\ShowOwnBillingInvoiceAction;
use App\Actions\Billing\SubmitManualPaymentAction;
use App\DTOs\Billing\SubmitManualPaymentDTO;
use App\Enums\Billing\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ListOwnBillingInvoicesRequest;
use App\Http\Requests\Billing\RequestRenewalInvoiceRequest;
use App\Http\Requests\Billing\SubmitBillingPaymentRequest;
use App\Http\Resources\Billing\BillingInvoiceResource;
use App\Http\Resources\Billing\BillingPaymentResource;
use App\Http\Resources\Billing\TenantSubscriptionResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * The shop's own subscription & billing (PRD-04 / ENTI-3.3), tenant permission `billing.manage`:
 *   GET  /api/v1/billing/subscription
 *   GET  /api/v1/billing/invoices            GET /api/v1/billing/invoices/{id}
 *   POST /api/v1/billing/invoices/{id}/payments   (multipart receipt; allowed while read-only / past due)
 *   POST /api/v1/billing/renewal-invoice          (allowed while read-only / past due)
 * Every lookup is scoped to the tenant resolved for the request, never to an id from the client.
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly GetTenantSubscriptionAction $subscriptionAction,
        private readonly ListOwnBillingInvoicesAction $listAction,
        private readonly ShowOwnBillingInvoiceAction $showAction,
        private readonly SubmitManualPaymentAction $submitAction,
        private readonly RequestRenewalInvoiceAction $renewalAction,
        private readonly CancelOwnBillingPaymentAction $cancelPaymentAction,
    ) {}

    public function subscription(): JsonResponse
    {
        return (new TenantSubscriptionResource($this->subscriptionAction->execute($this->tenant())))
            ->additional(['success' => true])
            ->response();
    }

    public function invoices(ListOwnBillingInvoicesRequest $request): JsonResponse
    {
        return BillingInvoiceResource::collection($this->listAction->execute($this->tenant(), $request->perPage()))
            ->additional(['success' => true])
            ->response();
    }

    public function showInvoice(int $id): JsonResponse
    {
        return (new BillingInvoiceResource($this->showAction->execute($this->tenant(), $id)))
            ->additional(['success' => true])
            ->response();
    }

    public function submitPayment(SubmitBillingPaymentRequest $request, int $id): JsonResponse
    {
        $tenant = $this->tenant();

        $payment = $this->submitAction->execute(SubmitManualPaymentDTO::fromArray(
            $request->safe()->except('receipt'),
            (string) $tenant->getKey(),
            $id,
            $request->file('receipt'),
            $request->user()?->getKey() === null ? null : (int) $request->user()->getKey(),
        ));

        return (new BillingPaymentResource($payment))
            ->additional(['success' => true, 'message' => __('billing.messages.payment_submitted')])
            ->response()
            ->setStatusCode(201);
    }

    public function cancelPayment(int $id): JsonResponse
    {
        $payment = $this->cancelPaymentAction->execute((string) $this->tenant()->getKey(), $id);

        return (new BillingPaymentResource($payment))
            ->additional(['success' => true, 'message' => __('billing.messages.payment_cancelled')])
            ->response();
    }

    public function renewalInvoice(RequestRenewalInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->renewalAction->execute($this->tenant(), BillingCycle::from($request->validated('cycle')));

        return (new BillingInvoiceResource($invoice->load('payments')))
            ->additional(['success' => true, 'message' => __('billing.messages.renewal_invoice_issued')])
            ->response()
            ->setStatusCode(201);
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
