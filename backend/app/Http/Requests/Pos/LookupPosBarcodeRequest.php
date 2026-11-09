<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POS-01: GET /api/v1/pos/lookup?code= — needs `pos.access` and access to the ACTIVE store
 * (resolved by `store.active`; for `all` the user's own default store).
 */
final class LookupPosBarcodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
        ];
    }

    public function code(): string
    {
        return trim((string) $this->validated('code'));
    }

    public function activeStore(): Store
    {
        if ($this->resolvedActiveStore !== null) {
            return $this->resolvedActiveStore;
        }

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
