<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\UpdateUserDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateUserAction
{
    /**
     * Update user details, optional password and sync role
     */
    public function execute(UpdateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::findOrFail($dto->id);

            $data = [
                'name'             => $dto->name,
                'phone'            => $dto->phone,
                'email'            => $dto->email,
                'default_store_id' => $dto->default_store_id,
                'is_active'        => $dto->is_active,
            ];

            if ($dto->password) {
                $data['password'] = Hash::make($dto->password);
            }

            $user->update($data);
            $user->syncRoles([$dto->role]);

            return $user;
        });
    }
}
