<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $payment_number
 * @property int|null $customer_id
 * @property int|null $supplier_id
 * @property int|null $invoice_id
 * @property int|null $purchase_id
 * @property int|null $store_id
 * @property int|null $user_id
 * @property string $amount
 * @property Carbon|null $payment_date
 * @property string $payment_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /** Number prefix of the vouchers InvoiceService writes when an invoice is issued or edited. */
    public const SALE_NUMBER_PREFIX = 'PAY-INV-';

    protected $fillable = [
        'payment_number',
        'customer_id',
        'supplier_id',
        'invoice_id',
        'purchase_id',
        'store_id',
        'user_id',
        'amount',
        'payment_date',
        'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Vouchers of one branch drawer. A voucher without payments.store_id (written before the
     * column existed, or by a path that does not set it) belongs to the store of its invoice /
     * purchase; an unlinked one (on-account receipt / supplier payment) to its user's default
     * store, and to the main store when that user has none. Each voucher maps to one store.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeForStore(Builder $query, int $storeId): void
    {
        $isMainStore = Store::getMainStore()?->id === $storeId;

        $query->where(function (Builder $q) use ($storeId, $isMainStore): void {
            $q->where('payments.store_id', $storeId)
                ->orWhere(function (Builder $legacy) use ($storeId, $isMainStore): void {
                    $legacy->whereNull('payments.store_id')
                        ->where(function (Builder $doc) use ($storeId, $isMainStore): void {
                            $doc->whereIn('payments.invoice_id', Invoice::withTrashed()->select('id')->where('store_id', $storeId))
                                ->orWhereIn('payments.purchase_id', Purchase::withTrashed()->select('id')->where('store_id', $storeId))
                                ->orWhere(function (Builder $unlinked) use ($storeId, $isMainStore): void {
                                    $unlinked->whereNull('payments.invoice_id')
                                        ->whereNull('payments.purchase_id')
                                        ->where(function (Builder $owner) use ($storeId, $isMainStore): void {
                                            $owner->whereIn('payments.user_id', User::withTrashed()->select('id')->where('default_store_id', $storeId));

                                            if ($isMainStore) {
                                                $owner->orWhereNull('payments.user_id')
                                                    ->orWhereIn('payments.user_id', User::withTrashed()->select('id')->whereNull('default_store_id'));
                                            }
                                        });
                                });
                        });
                });
        });
    }

    /**
     * Excludes vouchers of cancelled invoices (a cancelled sale's cash is not in the drawer).
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeExcludingCancelledInvoices(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('payments.invoice_id')
                ->orWhereNotIn('payments.invoice_id', Invoice::withTrashed()->select('id')->where('status', 'cancelled'));
        });
    }

    /**
     * Customer vouchers written by the invoice itself (cash taken at the sale).
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeCollectedAtSale(Builder $query): void
    {
        $query->whereNotNull('payments.customer_id')
            ->where('payments.payment_number', 'like', self::SALE_NUMBER_PREFIX.'%');
    }

    /**
     * Customer receipts (debt collection / later payment of an invoice), never the sale's own voucher.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeCustomerReceipts(Builder $query): void
    {
        $query->whereNotNull('payments.customer_id')
            ->where('payments.payment_number', 'not like', self::SALE_NUMBER_PREFIX.'%');
    }
}
