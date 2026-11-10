<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central DB: a shop's request to change plan or buy an add-on through support.
 *
 * @property int $id
 * @property string $tenant_id
 * @property PlanChangeRequestKind $kind
 * @property int|null $current_plan_id
 * @property int|null $requested_plan_id
 * @property int|null $addon_id
 * @property int $quantity
 * @property BillingCycle $billing_cycle
 * @property string|null $notes
 * @property PlanChangeRequestStatus $status
 * @property int|null $billing_invoice_id
 * @property string|null $rejection_reason
 * @property string|null $requested_by_name
 * @property int|null $requested_by_user_id tenant-side user id (another database, no FK)
 * @property int|null $reviewed_by central_users id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Plan|null $currentPlan
 * @property-read Plan|null $requestedPlan
 * @property-read Addon|null $addon
 * @property-read BillingInvoice|null $invoice
 */
class PlanChangeRequest extends Model
{
    use UsesCentralConnection;

    protected $table = 'plan_change_requests';

    protected $fillable = [
        'tenant_id',
        'kind',
        'current_plan_id',
        'requested_plan_id',
        'addon_id',
        'quantity',
        'billing_cycle',
        'notes',
        'status',
        'billing_invoice_id',
        'rejection_reason',
        'requested_by_name',
        'requested_by_user_id',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PlanChangeRequestKind::class,
            'status' => PlanChangeRequestStatus::class,
            'billing_cycle' => BillingCycle::class,
            'quantity' => 'integer',
            'current_plan_id' => 'integer',
            'requested_plan_id' => 'integer',
            'addon_id' => 'integer',
            'billing_invoice_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PlanChangeRequest>  $query
     * @return Builder<PlanChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PlanChangeRequestStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === PlanChangeRequestStatus::Pending;
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
    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * @return BelongsTo<BillingInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'reviewed_by');
    }
}
