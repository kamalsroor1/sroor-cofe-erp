<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POSB-6: GET /api/v1/pos/quick-keys — keys of the ACTIVE store (STOR-2: resolved by
 * `store.active` from X-Store-Id; for `all` the user's own default store). Needs `pos.access`
 * AND access to that store: a cashier sending another branch's X-Store-Id gets 403, never
 * that branch's layout.
 */
final class ListPosQuickKeysRequest extends FormRequest
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
        return [];
    }

    public function activeStore(): Store
    {
        if ($this->resolvedActiveStore !== null) {
            return $this->resolvedActiveStore;
        }

        // STOR-2: the store resolved and access-checked by `store.active`; in `all` mode the
        // user's own default accessible store (quick keys always belong to one branch).
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
