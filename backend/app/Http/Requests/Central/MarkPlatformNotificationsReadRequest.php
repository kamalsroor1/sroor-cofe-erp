<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/notifications/{id}/read and POST .../notifications/read-all
 * (#232 UI §4.2). Marks are per operator. super_admin.billing.view.
 */
final class MarkPlatformNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingView);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
