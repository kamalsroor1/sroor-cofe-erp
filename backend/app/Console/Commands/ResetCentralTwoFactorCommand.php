<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Central\TwoFactor\ResetCentralTwoFactorAction;
use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * OPS-20: `php artisan central:reset-2fa operator@example.com --reason="ticket 42, identity checked by video call"`,
 * for an operator who lost the phone
 * (and the recovery codes). Interactive on purpose: it shows who will be reset and asks for two
 * explicit confirmations (identity verified out-of-band, then reset). No --force/--no-interaction
 * bypass, so it cannot be run by a script by accident. See docs/07-operations/vps-runbook.md.
 */
final class ResetCentralTwoFactorCommand extends Command
{
    protected $signature = 'central:reset-2fa
        {email : Email of the operator who lost the authenticator}
        {--reason= : Why the reset is done (ticket / how the identity was verified); required, stored in the audit row}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.reset_2fa.description'));
    }

    public function handle(ResetCentralTwoFactorAction $action): int
    {
        if (! $this->input->isInteractive()) {
            $this->error((string) __('console.reset_2fa.interactive_only'));

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error((string) __('console.reset_2fa.reason_required'));

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = CentralUser::query()->where('email', $email)->first();

        if (! $user instanceof CentralUser) {
            $this->error((string) __('console.reset_2fa.not_found'));

            return self::FAILURE;
        }

        $this->line((string) __('console.reset_2fa.target', ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email]));

        if (! $this->confirm((string) __('console.reset_2fa.confirm_identity'), false)) {
            $this->warn((string) __('console.reset_2fa.aborted'));

            return self::FAILURE;
        }

        if (! $this->confirm((string) __('console.reset_2fa.confirm_reset', ['email' => $user->email]), false)) {
            $this->warn((string) __('console.reset_2fa.aborted'));

            return self::FAILURE;
        }

        try {
            $revoked = $action->execute($user->email, $this->osUser(), $reason);
        } catch (ModelNotFoundException) {
            $this->error((string) __('console.reset_2fa.not_found'));

            return self::FAILURE;
        } catch (QueryException) {
            // Never echo a QueryException: its message carries the bound values.
            $this->error((string) __('console.reset_2fa.failed_database'));

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error((string) __('console.reset_2fa.failed', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info((string) __('console.reset_2fa.done', ['email' => $user->email, 'sessions' => $revoked]));
        $this->line((string) __('console.reset_2fa.next_steps'));

        return self::SUCCESS;
    }

    /**
     * The OS account actually running the command (effective uid). get_current_user() returns the
     * OWNER of the script file, which is the deploy user for everyone who runs artisan.
     */
    private function osUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid(posix_geteuid());
            if (is_array($entry) && $entry['name'] !== '') {
                return $entry['name'];
            }
        }

        return get_current_user() ?: 'unknown';
    }
}
