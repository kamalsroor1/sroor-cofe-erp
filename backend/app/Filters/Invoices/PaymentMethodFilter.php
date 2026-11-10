<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PaymentMethodFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $paymentMethod = $this->request->input('payment_method');

        if ($paymentMethod && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        return $next($query);
    }
}
