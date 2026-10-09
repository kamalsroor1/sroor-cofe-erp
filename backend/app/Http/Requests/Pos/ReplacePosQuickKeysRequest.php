<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\PosQuickKeyColor;
use App\Enums\PosQuickKeyType;
use App\Http\Requests\Pos\Concerns\ResolvesTargetStore;
use App\Models\Category;
use App\Models\Item;
use App\Models\PosQuickKey;
use App\Models\StorePosSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POSB-6: PUT /api/v1/stores/{store}/pos/quick-keys — full replace of the store's layout.
 * settings.manage + access to {store} (same rule as the store's POS settings, POSB-2).
 *
 * Item/category existence is checked in bulk (one query each) on the default connection,
 * which is the tenant DB inside a tenant request, so an id from another tenant can only
 * match a row of THIS tenant (and the FKs back it up). Items must be active and not
 * soft-deleted; same for categories.
 */
final class ReplacePosQuickKeysRequest extends FormRequest
{
    use ResolvesTargetStore;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', [StorePosSetting::class, $this->targetStore()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keys' => ['present', 'array', 'max:'.PosQuickKey::MAX_KEYS],
            'keys.*' => ['required', 'array'],
            'keys.*.page' => ['required', 'integer', 'between:1,'.PosQuickKey::MAX_PAGES],
            'keys.*.position' => ['required', 'integer', 'between:0,'.PosQuickKey::MAX_POSITION],
            'keys.*.type' => ['required', 'string', Rule::enum(PosQuickKeyType::class)],
            'keys.*.item_id' => ['nullable', 'required_if:keys.*.type,'.PosQuickKeyType::Item->value, 'prohibited_unless:keys.*.type,'.PosQuickKeyType::Item->value, 'integer', 'min:1'],
            'keys.*.category_id' => ['nullable', 'required_if:keys.*.type,'.PosQuickKeyType::Category->value, 'prohibited_unless:keys.*.type,'.PosQuickKeyType::Category->value, 'integer', 'min:1'],
            'keys.*.label' => ['nullable', 'string', 'max:30'],
            'keys.*.color' => ['nullable', 'string', Rule::enum(PosQuickKeyColor::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectDuplicatePositions($validator),
            fn (Validator $validator) => $this->rejectUnavailableTargets($validator),
        ];
    }

    private function rejectDuplicatePositions(Validator $validator): void
    {
        $seen = [];
        foreach ($this->keyRows() as $index => $key) {
            if (! is_numeric($key['page'] ?? null) || ! is_numeric($key['position'] ?? null)) {
                continue;
            }

            $page = (int) $key['page'];
            $position = (int) $key['position'];
            $slot = $page.':'.$position;

            if (isset($seen[$slot])) {
                $validator->errors()->add("keys.{$index}.position", __('pos.quick_keys_duplicate_position', [
                    'page' => $page,
                    'position' => $position,
                ]));

                continue;
            }

            $seen[$slot] = true;
        }
    }

    private function rejectUnavailableTargets(Validator $validator): void
    {
        $itemIds = [];
        $categoryIds = [];
        foreach ($this->keyRows() as $index => $key) {
            $type = $key['type'] ?? null;
            if ($type === PosQuickKeyType::Item->value && is_numeric($key['item_id'] ?? null)) {
                $itemIds[$index] = (int) $key['item_id'];
            } elseif ($type === PosQuickKeyType::Category->value && is_numeric($key['category_id'] ?? null)) {
                $categoryIds[$index] = (int) $key['category_id'];
            }
        }

        if ($itemIds !== []) {
            $available = Item::query()->whereIn('id', array_unique($itemIds))->where('is_active', true)->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
            foreach (array_diff($itemIds, $available) as $index => $id) {
                $validator->errors()->add("keys.{$index}.item_id", __('pos.quick_keys_item_unavailable'));
            }
        }

        if ($categoryIds !== []) {
            $available = Category::query()->whereIn('id', array_unique($categoryIds))->where('is_active', true)->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
            foreach (array_diff($categoryIds, $available) as $index => $id) {
                $validator->errors()->add("keys.{$index}.category_id", __('pos.quick_keys_category_unavailable'));
            }
        }
    }

    /**
     * Raw `keys` rows (only array rows), indexed like the request.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function keyRows(): array
    {
        $keys = $this->input('keys');
        if (! is_array($keys) || count($keys) > PosQuickKey::MAX_KEYS) {
            return [];
        }

        return array_filter($keys, 'is_array');
    }
}
