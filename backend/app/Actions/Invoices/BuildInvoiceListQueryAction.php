<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Filters\Invoices\CustomerFilter;
use App\Filters\Invoices\DateRangeFilter;
use App\Filters\Invoices\IdsFilter;
use App\Filters\Invoices\PaymentMethodFilter;
use App\Filters\Invoices\PaymentTypeFilter;
use App\Filters\Invoices\SearchFilter;
use App\Filters\Invoices\StatusFilter;
use App\Filters\Invoices\StoreFilter;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

/**
 * The one filtered invoice query shared by the list (index) and the CSV export, so both
 * always apply exactly the same filters and store scoping.
 */
final class BuildInvoiceListQueryAction
{
    /**
     * @return Builder<Invoice>
     */
    public function execute(): Builder
    {
        /** @var Builder<Invoice> */
        return app(Pipeline::class)
            ->send(Invoice::query()->with(['customer:id,name,phone,current_balance', 'user:id,name', 'store:id,name']))
            ->through([
                StoreFilter::class,
                CustomerFilter::class,
                StatusFilter::class,
                PaymentTypeFilter::class,
                PaymentMethodFilter::class,
                DateRangeFilter::class,
                SearchFilter::class,
                IdsFilter::class,
            ])
            ->thenReturn();
    }
}
