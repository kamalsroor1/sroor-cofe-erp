<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\Csv\CsvCell;
use Illuminate\Database\Eloquent\Builder;

/**
 * Streams the filtered invoices as CSV (UTF-8 BOM so Excel reads Arabic) in id-ordered chunks,
 * so a large export never loads the whole result set into memory.
 */
final class ExportInvoicesCsvAction
{
    private const CHUNK_SIZE = 500;

    private const COLUMNS = ['number', 'customer', 'phone', 'store', 'date', 'payment_type', 'payment_method', 'net', 'paid', 'remaining', 'status'];

    /**
     * @param  Builder<Invoice>  $query
     * @param  resource  $out
     */
    public function execute(Builder $query, $out): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fwrite($out, CsvCell::row(array_map(fn (string $key): string => __("invoices.csv_columns.{$key}"), self::COLUMNS)));

        $query->reorder()->chunkByIdDesc(self::CHUNK_SIZE, function ($invoices) use ($out): void {
            foreach ($invoices as $invoice) {
                /** @var Invoice $invoice */
                fwrite($out, CsvCell::row([
                    $invoice->invoice_number,
                    $invoice->customer?->name ?: __('invoices.csv_cash_customer'),
                    $invoice->customer?->phone,
                    $invoice->store?->name,
                    $invoice->invoice_date?->toDateString(),
                    $this->paymentType($invoice->payment_type),
                    $invoice->payment_method,
                    (string) $invoice->net_total,
                    (string) $invoice->paid_amount,
                    (string) $invoice->remaining_amount,
                    $this->status($invoice->status),
                ]));
            }
        });
    }

    private function paymentType(?string $type): string
    {
        return match ($type) {
            'cash', 'credit', 'partial' => __("invoices.csv_payment_type.{$type}"),
            default => (string) $type,
        };
    }

    private function status(?string $status): string
    {
        return match ($status) {
            'confirmed', 'cancelled' => __("invoices.csv_status.{$status}"),
            default => (string) $status,
        };
    }
}
