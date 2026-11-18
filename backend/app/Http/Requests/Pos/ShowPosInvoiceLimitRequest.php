<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /pos/invoice-limit: same audience as /pos/bootstrap (admin, pos.access or invoices.create).
 */
final class ShowPosInvoiceLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('pos.access') || $user->can('invoices.create'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
