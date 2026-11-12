<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\SetUserPosPinDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SetUserPosPinAction
{
    public function __construct(
        private readonly RoleGrantGuard $roleGrantGuard,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * BE-08: set (or, with null, clear) the target user's manager approval PIN, stored hashed.
     * An actor without the `admin` role may not change an admin account's PIN (403), unless it is
     * their own.
     *
     * SEC-20: the PIN approves below-minimum-price sales and cash invoice cancellations in the
     * target's name, so a non-admin actor may only set another user's PIN when they already hold
     * every permission that user holds (403 otherwise). A change on another user's account is
     * written to the activity log (never the PIN). The target's sessions are deliberately kept:
     * the PIN is not a sign-in credential, and revoking would log a cashier out of the POS every
     * time a manager issues them a PIN.
     *
     * @throws AuthorizationException
     */
    public function execute(SetUserPosPinDTO $dto, User $actor): User
    {
        return DB::transaction(function () use ($dto, $actor): User {
            $target = User::query()->whereKey($dto->userId)->lockForUpdate()->firstOrFail();
            $isSelf = (int) $target->id === (int) $actor->id;

            if (! $isSelf && ! $actor->hasRole('admin') && $target->hasRole('admin')) {
                throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
            }

            $this->roleGrantGuard->assertCanTakeOverAccount($actor, $target);

            $target->forceFill(['pos_pin' => $dto->pin === null ? null : Hash::make($dto->pin)])->save();

            if (! $isSelf) {
                $this->activityLogService->log(
                    module: 'users',
                    action: $dto->pin === null ? 'pos_pin_cleared' : 'pos_pin_set',
                    description: __($dto->pin === null ? 'users.activity_pos_pin_cleared' : 'users.activity_pos_pin_set', [
                        'name' => $target->name,
                        'actor' => $actor->name,
                    ]),
                    subject: $target,
                );
            }

            return $target;
        });
    }
}
