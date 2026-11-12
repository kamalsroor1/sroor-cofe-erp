<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\CreateUserDTO;
use App\Enums\LimitResource;
use App\Models\User;
use App\Services\Entitlements\PlanEnforcer;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    public function __construct(
        private readonly PlanEnforcer $planEnforcer,
        private readonly RoleGrantGuard $roleGrantGuard,
    ) {}

    /**
     * Create user, hash password and assign role inside Transaction.
     *
     * SEC-04: a non-admin actor may only assign a role whose permissions they all hold (403),
     * and (SEC-20 review) only a default store they can access (403).
     * SELL-01: a user created by an actor is flagged `must_change_password`.
     * ROLE-01: `store_ids` (+ the default store) are assigned in the same transaction; each must be
     * a store the actor can access (403).
     */
    public function execute(CreateUserDTO $dto, ?User $actor = null): User
    {
        return DB::transaction(function () use ($dto, $actor) {
            // ROLE-01: the default store is the chosen one, else the first assigned branch; both
            // the default and the assigned branches land in store_user in this same transaction.
            $defaultStoreId = $dto->default_store_id ?? ($dto->store_ids[0] ?? null);
            $storeIds = self::withDefault($dto->store_ids, $defaultStoreId);

            if ($actor instanceof User) {
                $this->roleGrantGuard->assertCanAssignRole($actor, $dto->role);
                // SEC-20 review: a new account in a store the actor cannot access would be a way
                // into that store (the actor sets its password).
                foreach ($storeIds as $storeId) {
                    $this->roleGrantGuard->assertCanAssignStore($actor, $storeId);
                }
            }

            // SEC-06 / BE-12: plan users limit (active and inactive seats), counted under the tenant lock.
            $this->planEnforcer->assertWithinLimit(LimitResource::Users, fn (): int => User::query()->count());

            $user = new User([
                'name' => $dto->name,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'default_store_id' => $defaultStoreId,
                'is_active' => $dto->is_active,
            ]);
            // SELL-01: the creator chose this password, so the new user replaces it at first sign-in.
            $user->forceFill(['must_change_password' => $actor instanceof User]);
            $user->save();

            $user->syncRoles([$dto->role]);

            if ($storeIds !== []) {
                $user->stores()->sync($storeIds);
            }

            return $user;
        });
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<int>
     */
    private static function withDefault(array $storeIds, ?int $defaultStoreId): array
    {
        if ($defaultStoreId !== null) {
            $storeIds[] = $defaultStoreId;
        }

        return array_values(array_unique($storeIds));
    }
}
