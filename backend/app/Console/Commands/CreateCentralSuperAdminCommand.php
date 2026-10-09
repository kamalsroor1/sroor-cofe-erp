<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Central\SuperAdmins\CreateCentralSuperAdminAction;
use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * IDEN-1.6: `php artisan central:create-super-admin owner@example.com --name="Owner"`.
 *
 * The password is never an argument or option (shell history, process list): it is read
 * twice with secret(), must be at least 12 characters and is never printed. The email is
 * stored lowercase; the operator gets the central-guard `super_admin` role and must set up
 * 2FA on first sign-in.
 */
final class CreateCentralSuperAdminCommand extends Command
{
    public const MIN_PASSWORD_LENGTH = 12;

    protected $signature = 'central:create-super-admin
        {email : Operator email (stored lowercase)}
        {--name= : Display name (asked when omitted)}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.create_super_admin.description'));
    }

    public function handle(CreateCentralSuperAdminAction $action): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error((string) __('console.create_super_admin.invalid_email'));

            return self::FAILURE;
        }

        if (CentralUser::query()->where('email', $email)->exists()) {
            $this->error((string) __('console.create_super_admin.email_taken'));

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask((string) __('console.create_super_admin.ask_name'))));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error((string) __('console.create_super_admin.invalid_name'));

            return self::FAILURE;
        }

        $password = (string) $this->secret((string) __('console.create_super_admin.ask_password', ['min' => self::MIN_PASSWORD_LENGTH]));

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH || mb_strlen($password) > 255) {
            $this->error((string) __('console.create_super_admin.password_too_short', ['min' => self::MIN_PASSWORD_LENGTH]));

            return self::FAILURE;
        }

        if (! hash_equals($password, (string) $this->secret((string) __('console.create_super_admin.ask_password_confirmation')))) {
            $this->error((string) __('console.create_super_admin.password_mismatch'));

            return self::FAILURE;
        }

        try {
            $user = $action->execute($name, $email, $password);
        } catch (QueryException) {
            // Never echo a QueryException: its message carries the bound values (password hash).
            $this->error((string) __('console.create_super_admin.failed_database'));

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error((string) __('console.create_super_admin.failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info((string) __('console.create_super_admin.created', ['id' => $user->getKey(), 'email' => $user->email]));
        $this->line((string) __('console.create_super_admin.two_factor_hint'));

        return self::SUCCESS;
    }
}
