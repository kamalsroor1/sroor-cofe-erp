<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STOR-1: resolves and access-checks the active store (branch) of a tenant API request.
 *
 * Must run after authentication (ApiTokenAuth). Alias: `store.active`, mounted by STOR-2 on POS,
 * invoices, shifts, daily journal, transfers, treasury, reports and the dashboard.
 * `store.active:concrete` (checkout, invoice create, shift open/close, transfer create) also
 * refuses `X-Store-Id: all` with 403 `concrete_store_required`: a write needs exactly one branch.
 *
 *  - The `X-Store-Id` header is the ONLY source. A `store_id` in the body / query never wins.
 *  - `X-Store-Id: <id>`: the store must exist and the user must be allowed to access it
 *    (ActiveStore::canAccess, same rule as StorePolicy::view), else 403.
 *  - `X-Store-Id: all`: only for users who may see every store (ActiveStore::canViewAll), else 403.
 *  - Any other explicit value ("0", "abc", "1.5"…): 403, never a silent fallback.
 *  - No header: the user's default accessible store (ActiveStore::defaultFor); 403 when none.
 *  - An inactive store named explicitly is accepted for reads (reading a closed branch); the
 *    no-header fallback skips inactive stores. L1: `store.active:concrete` (a single-branch
 *    write) refuses an inactive store with 403 `store_inactive`: nothing is sold, received or
 *    booked on a closed branch.
 *
 * The resolved store is also written to `current_store_id` so legacy session readers see the
 * verified value only.
 */
final class ResolveActiveStore
{
    public function __construct(private readonly ActiveStore $activeStore) {}

    public const CONCRETE = 'concrete';

    /** error_code of the 403 a single-branch write gets on an inactive store (L1). */
    public const STORE_INACTIVE_CODE = 'store_inactive';

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $raw = $request->header('X-Store-Id');
        $hasHeader = is_string($raw) && trim($raw) !== '';

        if (! $hasHeader) {
            $default = ActiveStore::defaultFor($user);
            if (! $default instanceof Store) {
                return $this->forbidden();
            }

            if ($mode === self::CONCRETE && ! $default->is_active) {
                return $this->inactive();
            }

            $this->activate($default);

            return $next($request);
        }

        $parsed = ActiveStore::parseHeader($raw);

        if ($parsed === ActiveStore::ALL) {
            if (! ActiveStore::canViewAll($user)) {
                return $this->forbidden();
            }

            if ($mode === self::CONCRETE) {
                return response()->json([
                    'success' => false,
                    'message' => __('store_scope.concrete_store_required'),
                    'error_code' => ActiveStore::CONCRETE_REQUIRED_CODE,
                ], 403);
            }

            $this->activeStore->setAll();
            session()->forget('current_store_id');

            return $next($request);
        }

        if (! is_int($parsed) || ! ActiveStore::canAccess($user, $parsed)) {
            return $this->forbidden();
        }

        $store = Store::query()->find($parsed);
        if (! $store instanceof Store) {
            return $this->forbidden();
        }

        if ($mode === self::CONCRETE && ! $store->is_active) {
            return $this->inactive();
        }

        $this->activate($store);

        return $next($request);
    }

    private function activate(Store $store): void
    {
        $this->activeStore->set($store);
        session(['current_store_id' => (int) $store->id]);
    }

    /** L1: a single-branch write on a deactivated branch. */
    private function inactive(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('store_scope.store_inactive'),
            'error_code' => self::STORE_INACTIVE_CODE,
        ], 403);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('common.store_access_denied'),
            'error_code' => ClientStoreGuard::ERROR_CODE,
        ], 403);
    }
}
