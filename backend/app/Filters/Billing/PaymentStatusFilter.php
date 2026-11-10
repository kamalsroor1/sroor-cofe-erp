<?php

declare(strict_types=1);

namespace App\Filters\Billing;

use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingPayment;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * `?status=pending|verified|rejected` on the payment review queue. Unknown values are ignored
 * (the FormRequest already rejects them with 422).
 */
final class PaymentStatusFilter
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  Builder<BillingPayment>  $query
     * @return mixed
     */
    public function handle(Builder $query, Closure $next)
    {
        $status = BillingPaymentStatus::tryFrom((string) $this->request->query('status'));

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $next($query);
    }
}
