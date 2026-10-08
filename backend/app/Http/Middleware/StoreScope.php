<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StoreScope
{
    /**
     * Handle an incoming request.
     * Ensures session has an active current_store_id for the logged-in user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $currentStoreId = session('current_store_id');

            $validStore = null;
            if ($currentStoreId) {
                $validStore = Store::where('id', $currentStoreId)->where('is_active', true)->first();
            }

            if (! $validStore) {
                $defaultStore = $user->getCurrentStore();
                if ($defaultStore) {
                    session(['current_store_id' => $defaultStore->id]);
                }
            }
        }

        return $next($request);
    }
}
