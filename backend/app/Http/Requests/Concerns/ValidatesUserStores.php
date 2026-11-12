<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * ROLE-01: the branch fields of the user form. `store_ids` assigns the user to branches in the
 * same transaction as the save; `default_store_id` is the branch they land on. A user who is not
 * `admin` must end up with at least one branch, else 422 on `store_ids` (users.store_required):
 * a user without a branch is refused by every store-scoped endpoint.
 *
 * `store_ids` omitted or explicitly null means "not sent": on an update the user's current
 * assignment is kept (and then counts), on a create the user gets no extra branch. Only an array
 * (empty included) replaces the assignment.
 *
 * The role is resolved once (canonicalRole(), from prepareForValidation) and every later check
 * compares the STORED role name, never the raw request string: on a case-insensitive collation
 * "Admin" finds the `admin` role, so it must be treated (and authorized) as `admin`.
 */
trait ValidatesUserStores
{
    private ?Role $resolvedRole = null;

    /**
     * @return array<string, list<mixed>>
     */
    protected function storeRules(): array
    {
        $liveStore = Rule::exists('stores', 'id')->whereNull('deleted_at');

        return [
            'default_store_id' => ['nullable', 'integer', $liveStore],
            'store_ids' => ['sometimes', 'nullable', 'array'],
            'store_ids.*' => ['integer', 'distinct', $liveStore],
        ];
    }

    /**
     * Replaces the request's `role` with the stored name of the role it resolves to (web guard),
     * so authorize(), the rules, validated() and the Actions all see the canonical name.
     */
    protected function canonicalRole(): void
    {
        $raw = $this->input('role');
        if (! is_string($raw) || $raw === '') {
            return;
        }

        $role = Role::query()->where('name', $raw)->where('guard_name', 'web')->first();
        if ($role instanceof Role) {
            $this->resolvedRole = $role;
            $this->merge(['role' => $role->name]);
        }
    }

    protected function resolvesToAdminRole(): bool
    {
        return $this->resolvedRole instanceof Role && $this->resolvedRole->name === 'admin';
    }

    protected function requireStoreForNonAdmin(Validator $validator, ?User $existing = null): void
    {
        if ($validator->errors()->hasAny(['role', 'default_store_id', 'store_ids', 'store_ids.*'])) {
            return;
        }

        if ($this->resolvesToAdminRole()) {
            return;
        }

        $default = $this->input('default_store_id');
        if ($default !== null && $default !== '') {
            return;
        }

        $storeIds = $this->input('store_ids');
        if (is_array($storeIds)) {
            if ($storeIds !== []) {
                return;
            }
        } elseif ($existing instanceof User && $existing->stores()->exists()) {
            return;
        }

        $validator->errors()->add('store_ids', __('users.store_required'));
    }
}
