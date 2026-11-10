<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POS-04: GET /api/v1/pos/items — lean item page of the ACTIVE store (STOR-2: resolved by
 * `store.active` from X-Store-Id; for `all` the user's own default store). Needs `pos.access`
 * AND access to that store: a cashier sending another branch's X-Store-Id gets 403, never
 * that branch's stock or catalogue.
 */
final class ListPosItemsRequest extends FormRequest
{
    private ?Store $resolvedActiveStore = null;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('pos.access')
            && $user->can('view', $this->activeStore());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'only_in_stock' => ['nullable', 'boolean'],
            'only_low_stock' => ['nullable', 'boolean'],
            'is_weighted' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(ListPosItemsDTO::SORTS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            // Clamped (1..200) by the DTO, never rejected: a stale client must still get a page.
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
        ];
    }

    public function toDto(): ListPosItemsDTO
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return ListPosItemsDTO::fromArray($data, (int) $this->activeStore()->id);
    }

    public function activeStore(): Store
    {
        if ($this->resolvedActiveStore !== null) {
            return $this->resolvedActiveStore;
        }

        // STOR-2: the store resolved and access-checked by `store.active`; in `all` mode the
        $user = $this->user();
        $store = app(ActiveStore::class)->store()
            ?? ($user instanceof User ? ActiveStore::defaultFor($user) : null);

        if (! $store instanceof Store) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('pos.store_not_found'),
            ], 404));
        }

        return $this->resolvedActiveStore = $store;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('auth.unauthorized'),
        ], 403));
    }
}
