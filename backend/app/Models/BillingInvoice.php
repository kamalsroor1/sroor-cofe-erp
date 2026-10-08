<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Central DB: a SaaS subscription invoice (`billing_invoices`, ENTI-1.6).
 *
 * Not the POS `invoices` of a tenant DB. Issued by IssueSubscriptionInvoiceAction (ENTI-3.2)
 * with a gap-free number from BillingSequenceService; `lines` is the frozen snapshot of
 * what was billed. Money fields are DECIMAL(12,3) numeric strings.
 *
 * @property int $id
 * @property string $number
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property BillingInvoiceType $type
 * @property BillingInvoiceStatus $status
 * @property BillingCycle|null $billing_cycle
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property array<int, array<string, mixed>> $lines
 * @property string $subtotal DECIMAL(12,3) as a numeric string
 * @property string $discount DECIMAL(12,3) as a numeric string
 * @property string $tax_rate percentage, DECIMAL(6,3) as a numeric string (14.000 = 14%)
 * @property string $tax DECIMAL(12,3) as a numeric string
 * @property string $total DECIMAL(12,3) as a numeric string
 * @property string $currency ISO 4217
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property int|null $issued_by central_users id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Subscription|null $subscription
 * @property-read CentralUser|null $issuer
 * @property-read Collection<int, BillingPayment> $payments
 */
class BillingInvoice extends Model
{
    use UsesCentralConnection;

    protected $table = 'billing_invoices';

    protected $fillable = [
        'number',
        'tenant_id',
        'subscription_id',
        'type',
        'status',
        'billing_cycle',
        'period_start',
        'period_end',
        'lines',
        'subtotal',
        'discount',
        'tax_rate',
        'tax',
        'total',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'issued_by',
        'notes',
    ];

    /**
     * Mirrors the DB defaults so a model created without these attributes is
     * consistent before it is refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'subtotal' => '0.000',
        'discount' => '0.000',
        'tax_rate' => '0.000',
        'tax' => '0.000',
        'total' => '0.000',
        'currency' => Subscription::DEFAULT_CURRENCY,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_id' => 'integer',
            'issued_by' => 'integer',
            'type' => BillingInvoiceType::class,
            'status' => BillingInvoiceStatus::class,
            'billing_cycle' => BillingCycle::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'lines' => 'array',
            'subtotal' => 'decimal:3',
            'discount' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'tax' => 'decimal:3',
            'total' => 'decimal:3',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The super-admin who issued the invoice manually (ENTI-3.10); null when automatic.
     *
     * @return BelongsTo<CentralUser, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'issued_by');
    }

    /**
     * @return HasMany<BillingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }
}
