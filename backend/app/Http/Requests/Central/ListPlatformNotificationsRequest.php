<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/super-admin/notifications (#232 UI §4.2, the bell): `unread=1` lists unread items
 * only; `per_page` 1-100 (default 20). super_admin.billing.view (every item kind today is billing).
 */
final class ListPlatformNotificationsRequest extends FormRequest
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
        return [
            'unread' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function unreadOnly(): bool
    {
        return $this->boolean('unread');
    }

    public function perPage(): int
    {
        return max(1, min(100, (int) ($this->validated('per_page') ?? 20)));
    }
}
