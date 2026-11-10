<?php

declare(strict_types=1);

namespace App\Filters\Billing;

use App\Models\BillingPayment;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * `?tenant_id=<id>` on the payment review queue (bound parameter, no raw SQL).
 */
final class PaymentTenantFilter
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  Builder<BillingPayment>  $query
     * @return mixed
     */
    public function handle(Builder $query, Closure $next)
    {
        $tenantId = $this->request->query('tenant_id');

        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        return $next($query);
    }
}
