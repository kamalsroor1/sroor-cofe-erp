<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/super-admin/near-limit-shops (PLAN-14), super_admin.plans.manage (super-admin
 * role only; the read-only support role is refused).
 */
final class NearLimitShopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::PlansManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'threshold' => ['sometimes', 'integer', 'min:50', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }
}
