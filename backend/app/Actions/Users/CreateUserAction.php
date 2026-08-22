<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\CreateUserDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    /**
     * Create user, hash password and assign role inside Transaction
     */
    public function execute(CreateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name'             => $dto->name,
                'phone'            => $dto->phone,
                'email'            => $dto->email,
                'password'         => Hash::make($dto->password),
                'default_store_id' => $dto->default_store_id,
                'is_active'        => $dto->is_active,
            ]);

            $user->syncRoles([$dto->role]);

            return $user;
        });
    }
}
