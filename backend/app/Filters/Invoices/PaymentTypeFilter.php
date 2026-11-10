<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PaymentTypeFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $paymentType = $this->request->input('payment_type');

        if ($paymentType && $paymentType !== 'all') {
            $query->where('payment_type', $paymentType);
        }

        return $next($query);
    }
}
