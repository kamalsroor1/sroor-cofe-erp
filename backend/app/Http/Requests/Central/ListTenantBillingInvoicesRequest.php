<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/super-admin/tenants/{id}/billing-invoices: ?per_page=. super_admin.billing.view.
 */
final class ListTenantBillingInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingView);
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
