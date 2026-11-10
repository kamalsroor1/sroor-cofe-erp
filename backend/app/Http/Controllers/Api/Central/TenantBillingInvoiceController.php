<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\Actions\Billing\ListTenantBillingInvoicesAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IssueTenantInvoiceRequest;
use App\Http\Requests\Central\ListTenantBillingInvoicesRequest;
use App\Http\Resources\Billing\BillingInvoiceResource;
use App\Http\Resources\Billing\TenantSubscriptionSummaryResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Manual subscription invoices of one tenant (ENTI-3.10), routes/central.php:
 *   GET  /api/v1/super-admin/tenants/{id}/billing-invoices   super_admin.billing.view
 *   POST /api/v1/super-admin/tenants/{id}/billing-invoices   super_admin.billing.manage + step-up
 */
final class TenantBillingInvoiceController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly ListTenantBillingInvoicesAction $listAction,
        private readonly IssueSubscriptionInvoiceAction $issueAction,
    ) {}

    public function index(ListTenantBillingInvoicesRequest $request, string $id): JsonResponse
    {
        abort_unless(Tenant::query()->whereKey($id)->exists(), 404, __('super.tenant_not_found'));

        $result = $this->listAction->execute($id, $request->perPage());

        return BillingInvoiceResource::collection($result['invoices'])
            ->additional([
                'success' => true,
                'subscription' => (new TenantSubscriptionSummaryResource([
                    'tenant' => $result['tenant'],
                    'subscription' => $result['subscription'],
                ]))->resolve(),
            ])
            ->response();
    }

    public function store(IssueTenantInvoiceRequest $request, string $id): JsonResponse
    {
        abort_unless(Tenant::query()->whereKey($id)->exists(), 404, __('super.tenant_not_found'));

        $operator = $this->operator($request);
        $invoice = $this->issueAction->execute(
            IssueSubscriptionInvoiceDTO::fromArray($request->validated(), $id, (int) $operator->getKey()),
            $operator,
        );

        return (new BillingInvoiceResource($invoice->load(['payments', 'issuer'])))
            ->additional(['success' => true, 'message' => __('billing.messages.invoice_issued')])
            ->response()
            ->setStatusCode(201);
    }
}
