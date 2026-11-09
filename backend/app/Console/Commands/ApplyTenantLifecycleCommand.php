<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\ApplyTenantLifecycleAction;
use Illuminate\Console\Command;

/**
 * IDEN-3.6 / BE-05: daily subscription lifecycle sweep (scheduled in routes/console.php).
 *
 *   php artisan tenants:apply-lifecycle [--dry-run]
 */
final class ApplyTenantLifecycleCommand extends Command
{
    protected $signature = 'tenants:apply-lifecycle {--dry-run : List the moves without applying them}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.lifecycle.description'));
    }

    public function handle(ApplyTenantLifecycleAction $action): int
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $action->execute($dryRun);

        foreach ($result->applied as $move) {
            $this->line((string) __($dryRun ? 'console.lifecycle.would_move' : 'console.lifecycle.moved', $move));
        }
        foreach ($result->archiveDue as $tenantId) {
            $this->warn((string) __('console.lifecycle.archive_due', ['tenant_id' => $tenantId]));
        }
        foreach ($result->needsAttention as $item) {
            $this->warn((string) __('console.lifecycle.needs_attention', $item));
        }
        foreach ($result->failed as $failure) {
            $this->error((string) __('console.lifecycle.failed', $failure));
        }

        $this->info((string) __('console.lifecycle.summary', [
            'checked' => $result->checked,
            'moved' => count($result->applied),
            'archive_due' => count($result->archiveDue),
            'failed' => count($result->failed),
            'attention' => count($result->needsAttention),
        ]));

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
