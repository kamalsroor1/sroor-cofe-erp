<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountDeletion;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * MOB-05: POST /api/v1/super-admin/account-deletion-requests/{id}/handle, optional note (what was
 * done: deleted, refused after verification...). super_admin.tenants.manage.
 */
final class HandleAccountDeletionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
        ];
    }
}
