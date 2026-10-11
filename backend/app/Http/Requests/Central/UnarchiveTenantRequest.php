<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Models\TenantLifecycleEvent;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/tenants/{id}/unarchive (OPS-9): optional note, max 500.
 */
final class UnarchiveTenantRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:'.TenantLifecycleEvent::NOTE_MAX],
        ];
    }
}
