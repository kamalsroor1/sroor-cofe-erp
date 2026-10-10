<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    private const MONEY = ['numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'];

    private const MAX_LIMIT = 'max:1000000000';

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::PlansManage);
    }

    /**
     * Limits: null = unlimited. The legacy limit fields must be present (the existing
     * super-admin form always sends them); the ENTI-1.2 fields are optional so older
     * clients keep working and an omitted field is left untouched.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_key' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'price_monthly' => ['required', ...self::MONEY],
            'price_yearly' => ['required', ...self::MONEY],
            'founder_price_monthly' => ['sometimes', 'nullable', ...self::MONEY],
            'founder_price_yearly' => ['sometimes', 'nullable', ...self::MONEY],
            'max_users' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_stores' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_items' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_invoices_per_month' => ['present', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1', self::MAX_LIMIT],
            'max_warehouses' => ['sometimes', 'nullable', 'integer', 'min:0', self::MAX_LIMIT],
            'max_vans' => ['sometimes', 'nullable', 'integer', 'min:0', self::MAX_LIMIT],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'is_popular' => ['required', 'boolean'],
            'features' => ['required', 'array'],
        ];
    }

    /**
     * Money leaves the request as numeric strings, never floats (golden rule 1).
     *
     * @param  array-key|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        foreach (['price_monthly', 'price_yearly', 'founder_price_monthly', 'founder_price_yearly'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = $this->moneyString($data[$field]);
            }
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }

    private function moneyString(mixed $value): string
    {
        // JSON numbers decode to float; they were already validated to <= 3 decimals,
        // so formatting at scale 3 is exact.
        $string = is_float($value) ? sprintf('%.3F', $value) : (string) $value;

        return bcadd($string, '0', 3);
    }
}
