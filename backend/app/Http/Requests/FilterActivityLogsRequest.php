<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Logs\ActivityLogStoreScope;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class FilterActivityLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // SEC-1 (M1): tenant permission only; platform operators never read tenant logs here.
        return $user->can('logs.view') || $user->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable'],
            'store_id' => ['nullable'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * LOG-01: the branches the caller may read logs of. `store_id` = `all` (the SPA default) or
     * missing means "every branch I can read". A concrete id must be one of those branches
     * (ActiveStore::readableStoreIds, i.e. canRead: `stores.view_all` may filter on any branch),
     * else the same 403 as ClientStoreGuard (`store_access_denied`, or `no_store_assigned` for a
     * user assigned to no branch). `X-Store-Id` does not narrow the list: the page filters with `store_id`.
     */
    public function storeScope(): ActivityLogStoreScope
    {
        $user = $this->user();
        if (! $user instanceof User) {
            ClientStoreGuard::deny();
        }

        $scope = ActivityLogStoreScope::for($user);
        $raw = $this->input('store_id');

        if ($raw === null || $raw === '' || $raw === '0' || $raw === 0 || $raw === ActiveStore::ALL) {
            return $scope;
        }

        $storeId = ActiveStore::parseHeader(is_int($raw) ? (string) $raw : $raw);

        if (! is_int($storeId) || ! ($scope->seesEveryStore() || in_array($storeId, $scope->readableStoreIds ?? [], true))) {
            ClientStoreGuard::deny($user);
        }

        return new ActivityLogStoreScope($scope->readableStoreIds, $storeId);
    }
}
