<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RateLimitKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps in-flight POS / invoice write requests per tenant using non-blocking cache slot locks.
 *
 * Excess concurrent requests fail fast with HTTP 429 instead of queuing behind DB row locks
 * and starving shared php-fpm workers across other tenants.
 */
final class ThrottleTenantCheckoutConcurrency
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxConcurrent = (int) config('rate_limits.pos_checkout.max_concurrent_per_tenant', 10);
        if ($maxConcurrent <= 0) {
            return $next($request);
        }

        $ttl = max(1, (int) config('rate_limits.pos_checkout.lock_ttl_seconds', 30));
        $prefix = 'pos-checkout-concurrency|'.RateLimitKey::scope().'|';

        $acquiredLock = null;
        for ($slot = 1; $slot <= $maxConcurrent; $slot++) {
            $lock = Cache::lock($prefix.$slot, $ttl);
            if ($lock->get()) {
                $acquiredLock = $lock;
                break;
            }
        }

        if ($acquiredLock === null) {
            return response()->json([
                'success' => false,
                'message' => __('pos.too_many_concurrent_checkouts'),
                'retry_after' => 1,
            ], 429, [
                'Retry-After' => '1',
            ]);
        }

        try {
            return $next($request);
        } finally {
            $acquiredLock->release();
        }
    }
}
