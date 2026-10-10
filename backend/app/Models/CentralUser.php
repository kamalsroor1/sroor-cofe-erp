<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CentralUserFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Spatie\Permission\Traits\HasRoles;

/**
 * Platform operator (super-admin / support), IDEN-1.1.
 *
 * Deliberately standalone: it does NOT extend App\Models\User, so no tenant role,
 * store or POS logic applies to it, and a tenant user can never be one. It lives in
 * the CENTRAL `central_users` table, always on the central connection, uses the
 * `central` guard for roles/permissions, and issues tokens into
 * `central_personal_access_tokens` only.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property bool $must_reset_password W2-B3: set by central:migrate-super-admins, cleared by the reset flow
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CentralUser extends Authenticatable
{
    /** @use HasApiTokens<CentralPersonalAccessToken> */
    use HasApiTokens;

    /** @use HasFactory<CentralUserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /** IDEN-1.12: Fortify TOTP secret + encrypted recovery codes (written by Fortify's actions). */
    use TwoFactorAuthenticatable;

    protected $table = 'central_users';

    /**
     * spatie/laravel-permission: operator roles/permissions live on the `central` guard only.
     *
     * @var string
     */
    protected $guard_name = 'central';

    /**
     * Two-factor columns, login metadata and remember_token are written explicitly
     * (forceFill) by the owning Actions, never mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * IDEN-1.12: operator emails are stored trimmed and lowercase, so login, password reset
     * and the super-admin commands always compare the same value.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
        );
    }

    /**
     * Central users ALWAYS live in the central database, even while a tenant is initialized.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * Central tokens are stored in `central_personal_access_tokens`, never in
     * Sanctum's global token table.
     *
     * @return MorphMany<CentralPersonalAccessToken, $this>
     */
    public function tokens(): MorphMany
    {
        return $this->morphMany(CentralPersonalAccessToken::class, 'tokenable');
    }

    /**
     * Issue a central token. It always expires (config `central.token_ttl_minutes`,
     * default 240) and defaults to the `central:*` ability instead of Sanctum's `*`.
     *
     * @param  array<int, string>|null  $abilities
     */
    public function createToken(string $name, ?array $abilities = null, ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities ?? [(string) config('central.token_ability', 'central:*')],
            'expires_at' => $expiresAt ?? now()->addMinutes(self::tokenTtlMinutes()),
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

    /** Central token lifetime in minutes; a zero/negative config value falls back to 240. */
    public static function tokenTtlMinutes(): int
    {
        $minutes = (int) config('central.token_ttl_minutes', 240);

        return $minutes > 0 ? $minutes : 240;
    }

    protected static function newFactory(): CentralUserFactory
    {
        return CentralUserFactory::new();
    }
}
