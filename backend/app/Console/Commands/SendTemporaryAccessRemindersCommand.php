<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\SendTemporaryAccessRemindersAction;
use Illuminate\Console\Command;

/**
 * #232 spec E: remind the platform operators 6 hours before a shop's 48-hour temporary access
 * ends without a review decision (panel item + e-mail, once per window). Scheduled every
 * 15 minutes in routes/console.php. Idempotent.
 *
 *   php artisan billing:send-temporary-access-reminders
 */
final class SendTemporaryAccessRemindersCommand extends Command
{
    protected $signature = 'billing:send-temporary-access-reminders';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.temporary_access_reminders.description'));
    }

    public function handle(SendTemporaryAccessRemindersAction $action): int
    {
        // Central only: billing rows live in the central DB.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $sent = $action->execute();

        $this->info((string) __('console.temporary_access_reminders.summary', ['sent' => $sent]));

        return self::SUCCESS;
    }
}
