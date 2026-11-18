<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\FeatureOverrideMode;
use App\Models\PlanFeature;
use App\Models\TenantFeatureOverride;
use App\Support\PlatformSuperAdmin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/v1/super-admin/tenants/{id}/feature-overrides/{feature} (PLAN-12).
 * The feature must exist in the catalog (plan_features); a legacy alias is accepted and
 * stored under its canonical key.
 */
final class SetTenantFeatureOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['feature' => self::canonical((string) $this->route('feature'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['required', 'string', 'max:100', self::catalogRule()],
            'mode' => ['required', 'string', Rule::in(FeatureOverrideMode::values())],
            'reason' => ['required', 'string', 'min:3', 'max:'.TenantFeatureOverride::REASON_MAX],
            'expires_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addYears(10)->toDateTimeString()],
        ];
    }

    /** Resolve a legacy alias (config/entitlements.php) to the canonical key. */
    public static function canonical(string $feature): string
    {
        $mapped = ((array) config('entitlements.aliases', []))[$feature] ?? $feature;

        return is_string($mapped) && $mapped !== '' ? $mapped : $feature;
    }

    /** The key must be a catalog feature. */
    public static function catalogRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! PlanFeature::query()->where('key', $value)->exists()) {
                $fail((string) __('plan_overrides.feature_unknown'));
            }
        };
    }
}
