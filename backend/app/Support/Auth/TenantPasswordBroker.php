<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SIGN-15: the password broker of the CURRENT shop (tenant) users.
 *
 * Laravel's PasswordBrokerManager caches one broker per name, and that broker keeps the database
 * connection object it was built with; a queue worker or a long-lived process serving several
 * shops would then read and write another shop's token table. This factory builds a fresh broker
 * on every call, on the tenant connection resolved NOW (the connection of App\Models\User).
 *
 * Config: auth.passwords.tenant_users (table, expire minutes, throttle seconds). Tokens are stored
 * hashed (bcrypt) and the broker deletes them on a successful reset, so each is single-use.
 */
final class TenantPasswordBroker
{
    public function __construct(private readonly Hasher $hasher) {}

    public function make(): PasswordBrokerContract
    {
        $config = (array) config('auth.passwords.tenant_users', []);

        $repository = new DatabaseTokenRepository(
            DB::connection((new User)->getConnectionName()),
            $this->hasher,
            self::table(),
            self::hashKey(),
            self::expireMinutes() * 60,
            (int) ($config['throttle'] ?? 60),
        );

        return new PasswordBroker($repository, Auth::createUserProvider((string) ($config['provider'] ?? 'users')));
    }

    public static function table(): string
    {
        return (string) config('auth.passwords.tenant_users.table', 'password_reset_tokens');
    }

    public static function expireMinutes(): int
    {
        return max(1, (int) config('auth.passwords.tenant_users.expire', 30));
    }

    private static function hashKey(): string
    {
        $key = (string) config('app.key');

        return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7)) : $key;
    }
}
