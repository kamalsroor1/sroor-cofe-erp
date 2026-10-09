<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\Billing\CreditLedgerException;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Central DB: one append-only movement of a tenant's credits for one credits add-on
 * (`tenant_credit_ledger`, ENTI-1.10).
 *
 * Written only through App\Services\Billing\CreditBalanceService (which locks the
 * (tenant, addon_key) sentinel and checks the balance). Rows are never updated or deleted:
 * the model events and the query builder below throw CreditLedgerException::immutable(),
 * and production grants the app user no UPDATE/DELETE on the table. A correction is a new
 * compensating row.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $addon_key
 * @property string $delta DECIMAL(12,3) string, positive (allowance/top_up) or negative (usage/expiry)
 * @property string $reason one of self::REASONS
 * @property int|null $billing_invoice_id
 * @property Carbon|null $created_at
 * @property-read BillingInvoice|null $billingInvoice
 */
class TenantCreditLedgerEntry extends Model
{
    use UsesCentralConnection;

    public const REASON_ALLOWANCE = 'allowance';

    public const REASON_TOP_UP = 'top_up';

    public const REASON_USAGE = 'usage';

    public const REASON_EXPIRY = 'expiry';

    /** Reasons that add credits. */
    public const CREDIT_REASONS = [self::REASON_ALLOWANCE, self::REASON_TOP_UP];

    /** Reasons that remove credits. */
    public const DEBIT_REASONS = [self::REASON_USAGE, self::REASON_EXPIRY];

    public const REASONS = [self::REASON_ALLOWANCE, self::REASON_TOP_UP, self::REASON_USAGE, self::REASON_EXPIRY];

    /** Append-only: there is no updated_at column. */
    public const UPDATED_AT = null;

    protected $table = 'tenant_credit_ledger';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'addon_key',
        'delta',
        'reason',
        'billing_invoice_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'decimal:3',
            'billing_invoice_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw CreditLedgerException::immutable();
        });

        static::deleting(static function (): never {
            throw CreditLedgerException::immutable();
        });
    }

    /**
     * Every Eloquent path that would rewrite or remove existing rows throws.
     *
     * @param  QueryBuilder  $query
     * @return Builder<TenantCreditLedgerEntry>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new
        /**
         * @extends Builder<TenantCreditLedgerEntry>
         */
        class($query) extends Builder
        {
            /**
             * @param  array<string, mixed>  $values
             */
            public function update(array $values): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  array<int|string, mixed>  $values
             * @param  array<int, string>|string  $uniqueBy
             * @param  array<int|string, mixed>|null  $update
             */
            public function upsert(array $values, $uniqueBy, $update = null): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  string|null  $column
             */
            public function touch($column = null): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  string  $column
             * @param  float|int  $amount
             * @param  array<string, mixed>  $extra
             */
            public function increment($column, $amount = 1, array $extra = []): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  string  $column
             * @param  float|int  $amount
             * @param  array<string, mixed>  $extra
             */
            public function decrement($column, $amount = 1, array $extra = []): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  array<string, float|int|numeric-string>  $columns
             * @param  array<string, mixed>  $extra
             */
            public function incrementEach(array $columns, array $extra = []): int
            {
                throw CreditLedgerException::immutable();
            }

            /**
             * @param  array<string, float|int|numeric-string>  $columns
             * @param  array<string, mixed>  $extra
             */
            public function decrementEach(array $columns, array $extra = []): int
            {
                throw CreditLedgerException::immutable();
            }

            public function delete(): mixed
            {
                throw CreditLedgerException::immutable();
            }

            public function forceDelete(): mixed
            {
                throw CreditLedgerException::immutable();
            }

            public function truncate(): void
            {
                throw CreditLedgerException::immutable();
            }
        };
    }

    /**
     * @return BelongsTo<BillingInvoice, $this>
     */
    public function billingInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class);
    }
}
