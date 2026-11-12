<?php

declare(strict_types=1);

namespace App\Actions\Perf;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\DTOs\Perf\PerfSeedOptions;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerBalanceService;
use App\Services\SupplierBalanceService;
use App\Support\Perf\PerfDatasetBuilder;
use App\Support\Perf\QuietInstrumentation;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PERF-04: fill the CURRENT tenant (call it inside $tenant->run()) with a deterministic
 * performance dataset (see PerfDatasetBuilder for its shape and invariants).
 *
 * - Idempotent: the run leaves a marker in `settings`. A second run with the same options
 *   writes nothing and answers `already_seeded`; with other options it answers `conflict`.
 *   There is deliberately no "wipe and re-seed" option: deleting rows of a tenant that may
 *   also hold real or hand-made test data is not something a perf tool should ever do. Seed
 *   a fresh tenant instead.
 * - Atomic: the whole dataset, the party balances, the number counters and the marker are one
 *   transaction (bulk INSERTs in chunks inside it). A failed run leaves the tenant untouched.
 * - Quiet: Telescope / Pulse / query events / model events are off while it runs.
 *
 * The environment guard (local / testing only) lives in the command, before any tenant is
 * touched. On top of it the action refuses a tenant that already holds invoices or purchases.
 */
final class SeedPerfDatasetAction
{
    public const MARKER_KEY = 'perf_seed_marker';

    private const MARKER_VERSION = 1;

    public function __construct(
        private readonly TenantClock $clock,
        private readonly ResolveWalkInCustomerAction $walkInCustomer,
        private readonly CustomerBalanceService $customerBalances,
        private readonly SupplierBalanceService $supplierBalances,
    ) {}

    /**
     * @return array{status: 'seeded'|'already_seeded'|'conflict', counts: array<string, int>, options: array<string, int>}
     */
    public function execute(PerfSeedOptions $options): array
    {
        $marker = $this->marker();
        if ($marker !== null) {
            /** @var array<string, int> $previous */
            $previous = (array) ($marker['options'] ?? []);
            /** @var array<string, int> $counts */
            $counts = (array) ($marker['counts'] ?? []);

            return [
                'status' => $previous === $options->toArray() ? 'already_seeded' : 'conflict',
                'counts' => $counts,
                'options' => $previous,
            ];
        }

        // A tenant that already holds documents is somebody's data (possibly a restored
        // production dump on a dev machine): never mix generated rows into it.
        if (DB::table('invoices')->exists() || DB::table('purchases')->exists()) {
            throw new RuntimeException((string) __('console.perf_seed.tenant_not_empty'));
        }

        $userId = (int) User::query()->where('is_active', true)->orderBy('id')->value('id');
        if ($userId === 0) {
            throw new RuntimeException((string) __('console.perf_seed.no_user'));
        }

        $counts = QuietInstrumentation::run(fn (): array => DB::transaction(function () use ($options, $userId): array {
            $builder = new PerfDatasetBuilder($options, $this->clock, $userId, (int) $this->walkInCustomer->execute()->getKey());
            $counts = $builder->build();

            // The app's own formulas (invoices - payments - returns + opening) set the balances.
            foreach ($builder->customerIds() as $customerId) {
                $this->customerBalances->updateBalance($customerId);
            }
            foreach ($builder->supplierIds() as $supplierId) {
                $this->supplierBalances->updateBalance($supplierId);
            }

            Setting::set(self::MARKER_KEY, (string) json_encode([
                'version' => self::MARKER_VERSION,
                'options' => $options->toArray(),
                'counts' => $counts,
                'seeded_at' => now()->toIso8601String(),
            ]));

            return $counts;
        }));

        if (DB::connection()->getDriverName() === 'mysql' || DB::connection()->getDriverName() === 'mariadb') {
            // Make the optimizer see the real row counts right away.
            foreach (['items', 'store_stocks', 'customers', 'suppliers', 'invoices', 'invoice_items', 'payments', 'returns', 'return_items', 'purchases', 'purchase_items', 'expenses', 'stock_movements'] as $table) {
                DB::statement("ANALYZE TABLE {$table}");
            }
        }

        return ['status' => 'seeded', 'counts' => $counts, 'options' => $options->toArray()];
    }

    /** @return array<string, mixed>|null */
    private function marker(): ?array
    {
        $raw = DB::table('settings')->where('key', self::MARKER_KEY)->whereNull('deleted_at')->value('value');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['options' => []];
    }
}
