<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StoreScope
{
    /**
     * Ensures the session has an active current_store_id for the logged-in TENANT user.
     *
     * No-op in central context (tenancy not initialized: `stores` is a tenant table) and
     * for anything that is not an App\Models\User (a platform CentralUser on Horizon /
     * Telescope / Pulse has no stores).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancyInitialized() || ! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $currentStoreId = session('current_store_id');

        $validStore = null;
        if ($currentStoreId) {
            $validStore = Store::query()->where('id', $currentStoreId)->where('is_active', true)->first();
        }

        if (! $validStore) {
            $defaultStore = $user->getCurrentStore();
            if ($defaultStore) {
                session(['current_store_id' => $defaultStore->id]);
            }
        }

        return $next($request);
    }

    private function tenancyInitialized(): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }
}
