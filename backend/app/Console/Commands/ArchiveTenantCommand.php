<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\ArchiveTenantAction;
use App\DTOs\Tenants\PurgeTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Exceptions\TenantLifecycleException;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-9: synchronous archive of one suspended / cancelled tenant from the server console:
 * final backup (dump, encrypt, upload, verify on every disk) then `archived`, through the
 * same ArchiveTenantAction as the API job. Nothing is dropped; `tenants:purge` is separate.
 *
 *   php artisan tenants:archive <tenant id> [--note="…"]
 */
final class ArchiveTenantCommand extends Command
{
    protected $signature = 'tenants:archive
        {tenant : Tenant id}
        {--note= : Reason stored in the lifecycle history (max 500 characters)}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.archive.description'));
    }

    public function handle(ArchiveTenantAction $action, CentralAuditLogger $audit): int
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $id = trim((string) $this->argument('tenant'));
        $tenant = $id === '' ? null : Tenant::query()->find($id);
        if (! $tenant instanceof Tenant) {
            $this->error((string) __('console.purge.tenant_not_found', ['tenant' => $id]));

            return self::FAILURE;
        }

        $note = $this->option('note');
        $note = is_string($note) && trim($note) !== '' ? trim($note) : null;
        if ($note !== null && mb_strlen($note) > 500) {
            $this->error((string) __('console.archive.note_too_long'));

            return self::FAILURE;
        }

        $status = $tenant->lifecycleStatus();
        $refusal = match (true) {
            $tenant->isPurged() => TenantLifecycleException::tenantPurged()->getMessage(),
            ! $tenant->provisioningStatus()->isReady() => (string) __('provisioning.workspace_not_ready'),
            $status === null || ! $status->canBeArchived() => TenantLifecycleException::archiveNotAllowed((string) $tenant->status)->getMessage(),
            default => null,
        };
        if ($refusal !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        $context = ['source' => 'cli', 'operator' => PurgeTenantDTO::cliActor()];
        $audit->record(CentralAuditEvent::TenantArchiveRequested, $context + ['expected_status' => $status->value, 'note' => $note], subject: $tenant);

        $this->line((string) __('console.archive.started', ['tenant' => $id]));

        try {
            $archived = $action->execute($id, TenantLifecycleActor::System, [$status], null, $note, $context);
        } catch (TenantLifecycleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            report($e);
            $this->error((string) __('console.archive.failed', ['tenant' => $id]));

            return self::FAILURE;
        }

        $this->info((string) __('console.archive.done', [
            'tenant' => $id,
            'backup' => (string) $archived->final_backup_id,
        ]));

        return self::SUCCESS;
    }
}
