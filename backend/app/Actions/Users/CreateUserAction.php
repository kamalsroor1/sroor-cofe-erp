<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\CreateUserDTO;
use App\Models\User;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    public function __construct(private readonly RoleGrantGuard $roleGrantGuard) {}

    /**
     * Create user, hash password and assign role inside Transaction.
     *
     * SEC-04: a non-admin actor may only assign a role whose permissions they all hold (403).
     */
    public function execute(CreateUserDTO $dto, ?User $actor = null): User
    {
        return DB::transaction(function () use ($dto, $actor) {
            if ($actor instanceof User) {
                $this->roleGrantGuard->assertCanAssignRole($actor, $dto->role);
            }

            $user = User::create([
                'name' => $dto->name,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'default_store_id' => $dto->default_store_id,
                'is_active' => $dto->is_active,
            ]);

            $user->syncRoles([$dto->role]);

            return $user;
        });
    }
}
