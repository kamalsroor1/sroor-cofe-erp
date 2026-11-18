<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-12: POST /api/v1/super-admin/signup-requests/{id}/provision. Releases a verified
 * self-signup held in the manual queue (daily cap) with the owner's own password. No body.
 */
final class ProvisionSignupRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
