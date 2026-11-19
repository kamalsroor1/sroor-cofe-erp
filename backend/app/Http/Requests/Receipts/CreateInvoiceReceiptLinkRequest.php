<?php

declare(strict_types=1);

namespace App\Http\Requests\Receipts;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PRD-22: POST /api/v1/invoices/{id}/receipt-link. Same readers as GET /invoices/{id}
 * (invoices.view, or pos.access for the cashier sharing right after a sale). No body.
 */
final class CreateInvoiceReceiptLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ($user->hasRole('admin') || $user->can('invoices.view') || $user->can('pos.access'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
