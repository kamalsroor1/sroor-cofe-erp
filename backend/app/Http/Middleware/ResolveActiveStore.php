<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STOR-1: resolves and access-checks the active store (branch) of a tenant API request.
 *
 * Must run after authentication (ApiTokenAuth). Alias: `store.active`. Not mounted on any
 * route yet — STOR-2 (W3) mounts it on POS, invoices, shifts, transfers, treasury, reports.
 *
 *  - The `X-Store-Id` header is the ONLY source. A `store_id` in the body / query never wins.
 *  - `X-Store-Id: <id>`: the store must exist and the user must be allowed to access it
 *    (ActiveStore::canAccess, same rule as StorePolicy::view), else 403.
 *  - `X-Store-Id: all`: only for users who may see every store (ActiveStore::canViewAll), else 403.
 *  - Any other explicit value ("0", "abc", "1.5"…): 403, never a silent fallback.
 *  - No header: the user's default accessible store (ActiveStore::defaultFor); 403 when none.
 *  - An inactive store named explicitly is accepted (reading a closed branch); the no-header
 *    fallback skips inactive stores.
 *
 * The resolved store is also written to `current_store_id` so legacy session readers see the
 * verified value only.
 */
final class ResolveActiveStore
{
    public function __construct(private readonly ActiveStore $activeStore) {}

    public function handle(Request $request, Closure $next): Response
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

            $this->activate($default);

            return $next($request);
        }

        $parsed = ActiveStore::parseHeader($raw);

        if ($parsed === ActiveStore::ALL) {
            if (! ActiveStore::canViewAll($user)) {
                return $this->forbidden();
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

        $this->activate($store);

        return $next($request);
    }

    private function activate(Store $store): void
    {
        $this->activeStore->set($store);
        session(['current_store_id' => (int) $store->id]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('common.store_access_denied'),
        ], 403);
    }
}
