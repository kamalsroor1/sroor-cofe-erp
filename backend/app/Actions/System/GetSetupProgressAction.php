<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Models\CashShift;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * FE-08: "Getting started" checklist data for the dashboard. Cheap EXISTS/COUNT queries only,
 * returned for users who can act on it (admin / settings.manage); everyone else gets null.
 *
 * `shift` and `first_sale` are tenant-wide (any store), not scoped to the active store.
 * `branch_setup` is judged on the MAIN store (`is_main`), not the active store: name + phone + address filled.
 * Once every step is done the result is cached per tenant forever and the queries are skipped. The cache key
 * carries a version suffix: bump it whenever a step is added so tenants cached as "complete" are re-evaluated.
 */
final class GetSetupProgressAction
{
    private const CACHE_VERSION = 'v2';

    /**
     * @return array{company_info: bool, branch_setup: bool, categories: bool, items: bool, users: bool, shift: bool, first_sale: bool, completed: int, total: int}|null
     */
    public function execute(User $user): ?array
    {
        if (! $this->canView($user)) {
            return null;
        }

        $cacheKey = 'setup_progress_complete_'.self::CACHE_VERSION.'_'.(function_exists('tenant') && tenant('id') ? (string) tenant('id') : 'central');
        $complete = Cache::get($cacheKey);
        if (is_array($complete)) {
            return $complete;
        }

        $steps = [
            'company_info' => Setting::get('company_name') && Setting::get('company_phone') && Setting::get('company_address'),
            'branch_setup' => $this->mainBranchIsSetUp(),
            'categories' => Category::query()->exists(),
            'items' => Item::query()->exists(),
            'users' => User::query()->skip(1)->take(1)->exists(),
            'shift' => CashShift::query()->exists(),
            'first_sale' => Invoice::query()->where('status', 'confirmed')->exists(),
        ];
        $steps = array_map(fn (mixed $done): bool => (bool) $done, $steps);

        $result = $steps + [
            'completed' => count(array_filter($steps)),
            'total' => count($steps),
        ];

        if ($result['completed'] === $result['total']) {
            Cache::forever($cacheKey, $result);
        }

        return $result;
    }

    private function mainBranchIsSetUp(): bool
    {
        $store = Store::query()->where('is_main', true)->first(['name', 'phone', 'address']);

        return $store !== null
            && trim((string) $store->name) !== ''
            && trim((string) $store->phone) !== ''
            && trim((string) $store->address) !== '';
    }

    public function canView(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('settings.manage');
    }
}
