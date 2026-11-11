<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Refuses a CSV export that would stream more rows than `invoices.max_export_rows`.
 * It must run BEFORE the response starts streaming, otherwise the 422 can no longer be sent.
 */
final class InvoiceExportLimitGuard
{
    /**
     * @param  Builder<Invoice>  $query
     *
     * @throws ValidationException
     */
    public function assertWithinLimit(Builder $query): void
    {
        $max = (int) config('invoices.max_export_rows', 20000);
        if ($max > 0 && (clone $query)->count() > $max) {
            throw ValidationException::withMessages([
                'export' => __('invoices.export_limit_exceeded', ['max' => $max]),
            ]);
        }
    }
}
