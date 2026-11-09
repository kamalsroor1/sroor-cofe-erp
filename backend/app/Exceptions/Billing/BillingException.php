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
 * | paymentNotPending()      | 409  | billing.payment_not_pending      |
 * | invoiceNotVoidable()     | 409  | billing.invoice_not_voidable     |
 * | invoiceNotPayable()      | 409  | billing.invoice_not_payable      |
 * | tenantNotActivatable()   | 409  | billing.tenant_not_activatable   |
 * | paymentAlreadyPending()  | 422  | billing.payment_already_pending  |
 * | amountOutOfRange()       | 422  | billing.amount_out_of_range      |
 * | noPlan()                 | 422  | billing.no_plan                  |
 * | founderPriceUnavailable()| 422  | billing.founder_unavailable      |
 * | discountTooHigh()        | 422  | billing.discount_too_high        |
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
