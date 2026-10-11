<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Models\TenantRateLimitOverride;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/tenants/{id}/rate-limits (IDEN-4.6 ext).
 *
 * At least one raised limit; every value and the duration are capped by
 * config('central.rate_limit_override').
 */
final class RaiseTenantRateLimitRequest extends FormRequest
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
        $columns = array_keys(TenantRateLimitOverride::LIMITS);
        $maxPerMinute = max(1, (int) config('central.rate_limit_override.max_per_minute', 600));
        $maxDuration = max(1, (int) config('central.rate_limit_override.max_duration_minutes', 4320));

        $rules = [];
        foreach ($columns as $column) {
            $others = implode(',', array_values(array_diff($columns, [$column])));
            $rules[$column] = ['nullable', 'required_without_all:'.$others, 'integer', 'min:1', 'max:'.$maxPerMinute];
        }

        $rules['duration_minutes'] = ['required', 'integer', 'min:1', 'max:'.$maxDuration];
        $rules['reason'] = ['required', 'string', 'min:3', 'max:'.TenantRateLimitOverride::REASON_MAX];

        return $rules;
    }
}
