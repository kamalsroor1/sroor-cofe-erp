<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Models\BillingInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A manual-billing rule refused an operation (PRD-03 / PRD-04).
 *
 * Renders itself as { success:false, message:<translated>, error_code:"billing.<code>", details?:{…} }
 * and extends HttpException so it is never reported as a server error.
 *
 * | factory                  | HTTP | error_code                       |
 * |--------------------------|------|----------------------------------|
 * | duplicateInvoice()       | 409  | billing.invoice_duplicate        |
 * | openInvoiceExists() SELL-06 | 409 | billing.invoice_duplicate (details.open_invoice) |
 * | paymentNotPending()      | 409  | billing.payment_not_pending      |
 * | invoiceNotVoidable()     | 409  | billing.invoice_not_voidable     |
 * | invoiceNotPayable()      | 409  | billing.invoice_not_payable      |
 * | tenantNotActivatable()   | 409  | billing.tenant_not_activatable   |
 * | paymentAlreadyPending()  | 422  | billing.payment_already_pending  |
 * | amountOutOfRange()       | 422  | billing.amount_out_of_range      |
 * | noPlan()                 | 422  | billing.no_plan                  |
 * | founderPriceUnavailable()| 422  | billing.founder_unavailable      |
 * | discountTooHigh()        | 422  | billing.discount_too_high        |
 * | requestNotPending()      | 409  | billing.request_not_pending      |
 * | requestAlreadyPending()  | 422  | billing.request_already_pending  |
 * | requestSamePlan()        | 422  | billing.request_same_plan        |
 * | requestPlanUnavailable() | 422  | billing.request_plan_unavailable |
 * | requestAddonUnavailable()| 422  | billing.request_addon_unavailable|
 * | addonNeedsSubscription() | 422  | billing.addon_needs_subscription |
 * | differenceRequired()     | 422  | billing.difference_required      |
 * | addonNeedsActiveSubscription() | 422 | billing.addon_needs_active_subscription |
 * | differenceNeedsActiveTerm() | 409 | billing.difference_needs_active_term |
 * | renewalPlanMismatch()    | 409  | billing.renewal_plan_mismatch    |
 * | differenceTermChanged()  | 409  | billing.difference_term_changed  |
 * | renewalInFlight()        | 409  | billing.renewal_in_flight (details.invoice_id/number) |
 * | duplicatePayment()       | 409  | billing.payment_duplicate        |
 */
final class BillingException extends HttpException
{
    /**
     * @param  array<string, scalar|null>  $details  machine-readable context (no secrets, no other tenants' data)
     */
    private function __construct(int $status, private readonly string $errorCode, string $message, private readonly array $details = [])
    {
        parent::__construct($status, $message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function duplicateInvoice(BillingInvoice $existing): self
    {
        return new self(Response::HTTP_CONFLICT, 'billing.invoice_duplicate', (string) __('billing.errors.invoice_duplicate', ['number' => $existing->number]), [
            'invoice_id' => (int) $existing->getKey(),
            'invoice_number' => $existing->number,
        ]);
    }

    /**
     * SELL-06: the shop already has an unpaid plan / renewal / upgrade invoice (e.g. the auto-issued
     * trial-end invoice), so another one could be paid twice and extend the term twice. Same
     * error_code as duplicateInvoice() (the SPA already shows the existing invoice from details),
     * plus `open_invoice = true` and the existing invoice's type.
     */
    public static function openInvoiceExists(BillingInvoice $existing): self
    {
        return new self(Response::HTTP_CONFLICT, 'billing.invoice_duplicate', (string) __('billing.errors.open_invoice_exists', ['number' => $existing->number]), [
            'invoice_id' => (int) $existing->getKey(),
            'invoice_number' => $existing->number,
            'invoice_type' => $existing->type->value,
            'open_invoice' => true,
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function details(): array
    {
        return $this->details;
    }

    public static function paymentNotPending(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'payment_not_pending');
    }

    public static function invoiceNotVoidable(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'invoice_not_voidable');
    }

    public static function invoiceNotPayable(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'invoice_not_payable');
    }

    public static function tenantNotActivatable(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'tenant_not_activatable');
    }

    public static function paymentAlreadyPending(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'payment_already_pending');
    }

    public static function amountOutOfRange(string $remaining): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, 'billing.amount_out_of_range', (string) __('billing.errors.amount_out_of_range', ['remaining' => $remaining]), [
            'remaining' => $remaining,
        ]);
    }

    public static function noPlan(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'no_plan');
    }

    public static function founderPriceUnavailable(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'founder_unavailable');
    }

    public static function discountTooHigh(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'discount_too_high');
    }

    public static function requestNotPending(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'request_not_pending');
    }

    public static function requestAlreadyPending(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'request_already_pending');
    }

    public static function requestSamePlan(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'request_same_plan');
    }

    public static function requestPlanUnavailable(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'request_plan_unavailable');
    }

    public static function requestAddonUnavailable(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'request_addon_unavailable');
    }

    public static function addonNeedsSubscription(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'addon_needs_subscription');
    }

    public static function addonNeedsActiveSubscription(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'addon_needs_active_subscription');
    }

    public static function differenceNeedsActiveTerm(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'difference_needs_active_term');
    }

    public static function renewalPlanMismatch(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'renewal_plan_mismatch');
    }

    /** BILL-01 review: the term the difference priced has moved (a renewal was paid since it was issued). */
    public static function differenceTermChanged(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'difference_term_changed');
    }

    /** BILL-01 review: an open renewal invoice already has a payment, so a difference cannot be issued yet. */
    public static function renewalInFlight(BillingInvoice $renewal): self
    {
        return new self(Response::HTTP_CONFLICT, 'billing.renewal_in_flight', (string) __('billing.errors.renewal_in_flight', ['number' => $renewal->number]), [
            'invoice_id' => (int) $renewal->getKey(),
            'invoice_number' => $renewal->number,
        ]);
    }

    /** OPS-20: the same payment (amount + method + reference) was just recorded on this invoice. */
    public static function duplicatePayment(): self
    {
        return self::make(Response::HTTP_CONFLICT, 'payment_duplicate');
    }

    public static function differenceRequired(): self
    {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, 'difference_required');
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if ($this->details !== []) {
            $payload['details'] = $this->details;
        }

        return new JsonResponse($payload, $this->getStatusCode(), $this->getHeaders());
    }

    private static function make(int $status, string $code): self
    {
        return new self($status, 'billing.'.$code, (string) __('billing.errors.'.$code));
    }
}
