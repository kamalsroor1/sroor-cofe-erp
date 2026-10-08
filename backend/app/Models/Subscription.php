<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CENTRAL billing record of a tenant's plan (ENTI-1.3).
 *
 * `tenants.status` decides access; `subscriptions.status` is the billing log only.
 * Lifecycle dates (grace_ends_at, trial_extended_at) live on `tenants` (IDEN-3.2).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property BillingCycle $billing_cycle
 * @property SubscriptionStatus $status
 * @property string $amount DECIMAL(12,3) as a numeric string
 * @property string $currency ISO 4217, EGP by default
 * @property bool $price_locked price frozen for the paid term (existing subscribers keep it until renewal, Q-E5)
 * @property bool $is_founder holds one of the founder-pricing slots (ENTI-1.7)
 * @property Carbon|null $founder_price_until
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $cancelled_at
 * @property string|null $payment_method
 * @property array<string, mixed>|null $payment_details
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Plan|null $plan
 */
class Subscription extends Model
{
    use HasFactory;
    use UsesCentralConnection;

    public const DEFAULT_CURRENCY = 'EGP';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_cycle',
        'status',
        'amount',
        'currency',
        'price_locked',
        'is_founder',
        'founder_price_until',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'payment_method',
        'payment_details',
        'notes',
    ];

    /**
     * Mirrors the DB defaults so a model created without these attributes is
     * consistent before it is refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => self::DEFAULT_CURRENCY,
        'price_locked' => false,
        'is_founder' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:3',
            'price_locked' => 'boolean',
            'is_founder' => 'boolean',
            'founder_price_until' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_details' => 'array',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->ends_at->isFuture();
    }
}
