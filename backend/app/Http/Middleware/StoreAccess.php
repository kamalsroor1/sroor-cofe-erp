<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StoreAccess
{
    /**
     * Handle an incoming request.
     * Verifies the user has permission to access the target store.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Admin / Super Admin has access to all stores
            if ($user->hasRole('admin')) {
                return $next($request);
            }

            $storeId = $request->route('store_id') ?? $request->input('store_id') ?? session('current_store_id');

            if ($storeId) {
                $hasAccess = $user->stores()->where('stores.id', $storeId)->exists();
                if (! $hasAccess && (int) $user->default_store_id !== (int) $storeId) {
                    abort(403, 'غير مصرح لك بالوصول لبيانات هذا الفرع أو عربية التوزيع.');
                }
            }
        }

        return $next($request);
    }
}
