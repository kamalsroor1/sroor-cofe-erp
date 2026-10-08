<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central DB: a payment against a SaaS invoice (`billing_payments`, ENTI-1.6).
 *
 * `gateway_reference` is the unique idempotency key used by ActivateSubscriptionAction
 * (ENTI-3.4); NULL for a manual receipt that has none yet. `submitted_by` is a tenant
 * user id (no relation: it lives in the tenant DB).
 *
 * @property int $id
 * @property int $billing_invoice_id
 * @property string $tenant_id
 * @property string $amount DECIMAL(12,3) as a numeric string
 * @property string $currency ISO 4217
 * @property BillingPaymentMethod $method
 * @property BillingGateway $gateway
 * @property string|null $gateway_reference
 * @property BillingPaymentStatus $status
 * @property string|null $proof_path
 * @property int|null $submitted_by tenant user id
 * @property Carbon|null $paid_at
 * @property int|null $verified_by central_users id
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BillingInvoice $invoice
 * @property-read Tenant|null $tenant
 * @property-read CentralUser|null $verifier
 */
class BillingPayment extends Model
{
    use UsesCentralConnection;

    protected $table = 'billing_payments';

    protected $fillable = [
        'billing_invoice_id',
        'tenant_id',
        'amount',
        'currency',
        'method',
        'gateway',
        'gateway_reference',
        'status',
        'proof_path',
        'submitted_by',
        'paid_at',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'raw_payload',
    ];

    /**
     * Mirrors the DB defaults so a model created without these attributes is
     * consistent before it is refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => Subscription::DEFAULT_CURRENCY,
        'gateway' => 'manual',
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_invoice_id' => 'integer',
            'submitted_by' => 'integer',
            'verified_by' => 'integer',
            'amount' => 'decimal:3',
            'method' => BillingPaymentMethod::class,
            'gateway' => BillingGateway::class,
            'status' => BillingPaymentStatus::class,
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<BillingInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'verified_by');
    }
}
