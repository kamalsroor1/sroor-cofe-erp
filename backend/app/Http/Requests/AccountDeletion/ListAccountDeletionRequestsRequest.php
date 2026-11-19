<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountDeletion;

use App\Enums\AccountDeletionRequestStatus;
use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * MOB-05: GET /api/v1/super-admin/account-deletion-requests ?status=pending|handled|all&search=&per_page=.
 * super_admin.tenants.view.
 */
final class ListAccountDeletionRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsView);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([...AccountDeletionRequestStatus::values(), 'all'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
