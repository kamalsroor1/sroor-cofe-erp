<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/billing/plan-change-requests: ?per_page=. Tenant permission billing.manage.
 */
final class ListOwnPlanChangeRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('billing.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
