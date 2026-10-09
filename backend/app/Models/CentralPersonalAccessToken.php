<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access token of a platform operator (IDEN-1.1).
 *
 * Stored in the CENTRAL `central_personal_access_tokens` table, never in a tenant's
 * `personal_access_tokens`. Sanctum's global token model is left untouched, so the
 * tenant `auth:sanctum` stack can never resolve a central token (and vice versa).
 * Resolve central tokens with CentralPersonalAccessToken::findToken() only.
 *
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<int, string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $two_factor_verified_at last 2FA proof on THIS token (IDEN-1.12 step-up)
 */
class CentralPersonalAccessToken extends PersonalAccessToken
{
    protected $table = 'central_personal_access_tokens';

    /**
     * Merged with Sanctum's $casts (abilities, last_used_at, expires_at). The column is
     * written with forceFill() by the central 2FA actions only, never mass-assigned.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_factor_verified_at' => 'datetime',
        ];
    }

    /** True when a second factor was proven on this token within the last $minutes. */
    public function twoFactorVerifiedWithin(int $minutes): bool
    {
        $verifiedAt = $this->two_factor_verified_at;

        return $verifiedAt !== null && $verifiedAt->greaterThanOrEqualTo(now()->subMinutes($minutes));
    }

    /**
     * Central tokens ALWAYS live in the central database, even while a tenant is initialized.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
