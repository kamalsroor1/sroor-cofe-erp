<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos\Concerns;

use App\Models\Store;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Resolves the `{store}` route segment inside the FormRequest instead of implicit route-model
 * binding: SubstituteBindings runs before ApiTokenAuth in the v1 group, so a bound model would
 * answer 404 to unauthenticated callers (store-id enumeration). Resolving here happens after
 * authentication, inside the tenant connection, and excludes soft-deleted stores.
 */
trait ResolvesTargetStore
{
    private ?Store $resolvedTargetStore = null;

    public function targetStore(): Store
    {
        if ($this->resolvedTargetStore !== null) {
            return $this->resolvedTargetStore;
        }

        $id = $this->route('store');
        $store = is_numeric($id) ? Store::query()->find((int) $id) : null;

        if (! $store instanceof Store) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('pos.store_not_found'),
            ], 404));
        }

        return $this->resolvedTargetStore = $store;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('auth.unauthorized'),
        ], 403));
    }
}
