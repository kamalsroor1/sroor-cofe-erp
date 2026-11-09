<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\CreateUserDTO;
use App\Enums\LimitResource;
use App\Models\User;
use App\Services\Entitlements\PlanEnforcer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    public function __construct(private readonly PlanEnforcer $planEnforcer) {}

    /**
     * Create user, hash password and assign role inside Transaction
     */
    public function execute(CreateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            // SEC-06 / BE-12: plan users limit (active and inactive seats), counted under the tenant lock.
            $this->planEnforcer->assertWithinLimit(LimitResource::Users, fn (): int => User::query()->count());

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
