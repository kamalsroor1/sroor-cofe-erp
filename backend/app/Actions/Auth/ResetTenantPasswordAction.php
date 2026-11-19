<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ResetTenantPasswordDTO;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Auth\AccountLockout;
use App\Support\Auth\CurrentPasswordVerifier;
use App\Support\Auth\TenantPasswordBroker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-15: POST /api/v1/auth/reset-password on a shop host.
 *
 * One tenant transaction: the per-tenant broker checks the token (hashed, 30 min, single use),
 * then the user row is locked, the new password hashed in, the remember token rotated,
 * `must_change_password` cleared and EVERY Sanctum token of the user revoked (all devices signed
 * out); the broker deletes the reset token. After the commit the SEC-05 sign-in lockout and the
 * current-password bucket of the account are lifted (the owner is about to sign in).
 *
 * Unknown email, inactive account, bad, used or expired token, and "no shop resolved" all answer
 * the same 422 on `email` (auth.password_reset.invalid), so the endpoint reveals no account.
 */
final class ResetTenantPasswordAction
{
    public function __construct(
        private readonly TenantPasswordBroker $brokers,
        private readonly ActivityLogService $activityLogService,
        private readonly CurrentPasswordVerifier $currentPasswordVerifier,
    ) {}

    public function execute(ResetTenantPasswordDTO $dto): void
    {
        if (! tenancy()->initialized || ! tenancy()->tenant instanceof Tenant) {
            $this->fail();
        }

        $resetUser = null;

        // A real closure, not fn(): an arrow function would capture $resetUser by value.
        $status = DB::connection((new User)->getConnectionName())->transaction(
            function () use ($dto, &$resetUser): mixed {
                return $this->brokers->make()->reset(
                    ['email' => $dto->email, 'is_active' => true, 'token' => $dto->token, 'password' => $dto->password],
                    function (User $user, string $password) use (&$resetUser): void {
                        $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                        $locked->forceFill([
                            'password' => Hash::make($password),
                            'remember_token' => Str::random(60),
                            'must_change_password' => false,
                        ])->save();

                        $revoked = $locked->tokens()->delete();

                        $this->activityLogService->log(
                            module: 'auth',
                            action: 'password_reset_completed',
                            description: __('auth.password_reset.activity_completed', ['name' => $locked->name]),
                            subject: $locked,
                            properties: ['sessions_revoked' => (int) $revoked],
                            userId: (int) $locked->id,
                        );

                        event(new PasswordReset($locked));

                        $resetUser = $locked;
                    },
                );
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET || ! $resetUser instanceof User) {
            $this->fail();
        }

        // Cache only, after the commit (never rolled back with it).
        AccountLockout::clearForIdentifiers($resetUser->phone, $resetUser->email);
        $this->currentPasswordVerifier->passed($resetUser);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'email' => [__('auth.password_reset.invalid')],
        ]);
    }
}
