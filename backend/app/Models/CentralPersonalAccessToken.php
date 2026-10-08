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
 */
class CentralPersonalAccessToken extends PersonalAccessToken
{
    protected $table = 'central_personal_access_tokens';

    /**
     * Central tokens ALWAYS live in the central database, even while a tenant is initialized.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
