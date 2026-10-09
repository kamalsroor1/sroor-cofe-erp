<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('stores.manage') || $this->user()?->can('transfers.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'from_store_id' => ['required', 'different:to_store_id', 'exists:stores,id'],
            'to_store_id' => ['required', 'different:from_store_id', 'exists:stores,id'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    /**
     * Security: `transfers.create` alone must not let a branch move another branch's stock.
     * The source store must be accessible to the user (ActiveStore::canAccess); the destination
     * must be accessible too unless the user may see every store. Otherwise 403.
     */
    protected function passedValidation(): void
    {
        $user = $this->user();

        if (! $user instanceof User) {
            ClientStoreGuard::deny();
        }

        $fromStoreId = (int) $this->input('from_store_id');
        $toStoreId = (int) $this->input('to_store_id');

        if (! ActiveStore::canAccess($user, $fromStoreId)) {
            ClientStoreGuard::deny();
        }

        if (! ActiveStore::canAccess($user, $toStoreId) && ! ActiveStore::canViewAll($user)) {
            ClientStoreGuard::deny();
        }
    }
}
