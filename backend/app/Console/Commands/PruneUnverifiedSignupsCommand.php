<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Signup\PruneUnverifiedSignupsAction;
use Illuminate\Console\Command;

/**
 * SIGN-13: `php artisan signup:prune-unverified [--hours=24]`, scheduled daily (routes/console.php).
 * Deletes self-signups still awaiting email verification after the given hours (central DB only);
 * security review: also revokes expired tokens and scrubs secrets / IPs of old final rows.
 */
final class PruneUnverifiedSignupsCommand extends Command
{
    protected $signature = 'signup:prune-unverified {--hours=24 : Age in hours (at least 1) of the unverified signups to delete}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('self_signup.prune.description'));
    }

    public function handle(PruneUnverifiedSignupsAction $prune): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $result = $prune->execute(now()->subHours($hours));

        $this->info((string) __('self_signup.prune.done', ['count' => $result->deleted, 'hours' => $hours]));
        $this->info((string) __('self_signup.prune.scrubbed', ['tokens' => $result->tokensRevoked, 'count' => $result->scrubbed]));

        return self::SUCCESS;
    }
}
