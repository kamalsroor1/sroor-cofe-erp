<?php

namespace App\Models;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantProvisioningStatus;
use App\Enums\TenantStatus;
use App\Support\Tenancy\TenantLifecycleSnapshot;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use SensitiveParameter;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Virtual attributes stored in stancl's `data` JSON column (not custom columns):
 *
 * @property string|null $tenancy_db_name
 * @property string|null $tenancy_db_username
 * @property string|null $tenancy_db_password read decrypted (stored encrypted, see tenancyDbPassword())
 *
 * Custom columns (see getCustomColumns()):
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property string|null $phone
 * @property int|null $plan_id
 * @property string $status a App\Enums\TenantStatus value (string(20), IDEN-3.2); see lifecycleStatus()
 * @property Carbon|null $status_changed_at when the stored status was entered (anchors the lifecycle clocks)
 * @property Carbon|null $read_only_since when the tenant last became read-only
 * @property TenantSuspensionReason|null $suspension_reason why it was suspended/cancelled (CTO W1 Q2)
 * @property TenantStatus|null $status_before_archive restored by unarchive (CTO W1 Q2)
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $trial_extended_at the one-time +7-day trial extension was used (Q-L4)
 * @property Carbon|null $subscription_ends_at end of the PAID period; null for a trial (IDEN-3.2)
 * @property Carbon|null $grace_ends_at explicit end of the past_due grace
 * @property TenantProvisioningStatus|null $provisioning_status OPS-2; column default `ready` (null only on an unsaved/unrefreshed model)
 * @property int|null $provisioning_attempts
 * @property string|null $provisioning_error_code a App\Support\Tenancy\ProvisioningErrorCode value
 * @property Carbon|null $provisioning_started_at
 * @property Carbon|null $provisioned_at
 * @property array|null $enabled_features
 * @property array|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Domain> $domains
 * @property-read Collection<int, TenantLifecycleEvent> $lifecycleEvents
 *
 * @method static Builder<static> provisioned()
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'settings' => 'array',
        'enabled_features' => 'array',
        'features' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'read_only_since' => 'datetime',
        'trial_extended_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'suspension_reason' => TenantSuspensionReason::class,
        'status_before_archive' => TenantStatus::class,
        'provisioning_status' => TenantProvisioningStatus::class,
        'provisioning_attempts' => 'integer',
        'provisioning_started_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'email',
            'phone',
            'status',
            'plan_id',
            'trial_ends_at',
            'subscription_ends_at',
            'enabled_features',
            // Lifecycle (IDEN-3.2): real columns, never inside `data`.
            'status_changed_at',
            'grace_ends_at',
            'trial_extended_at',
            'read_only_since',
            'suspension_reason',
            'status_before_archive',
            // Queued provisioning (OPS-2): real columns, never inside `data`.
            'provisioning_status',
            'provisioning_attempts',
            'provisioning_error_code',
            'provisioning_started_at',
            'provisioned_at',
            // Every other real column of `tenants`: anything missing here is copied into `data`
            // on save and the copy overrides the column on hydration (stale updated_at, and an
            // array-cast `settings` re-encoded into ever-growing nested JSON on each save).
            'logo',
            'address',
            'commercial_register',
            'tax_number',
            'settings',
            'created_at',
            'updated_at',
        ];
    }

    // ========================================================================
    // التجهيز (Provisioning, OPS-2)
    // ========================================================================

    /**
     * The stored provisioning status. A model whose attribute was never loaded (created
     * without it, so the column default applied) is `ready`, like every pre-OPS-2 tenant.
     */
    public function provisioningStatus(): TenantProvisioningStatus
    {
        return $this->provisioning_status ?? TenantProvisioningStatus::Ready;
    }

    /** Only a provisioned workspace may be served (ResolveApiTenancy, workspace resolver). */
    public function isProvisioned(): bool
    {
        return $this->provisioningStatus()->isReady();
    }

    /**
     * A pending/running provisioning with no activity for longer than the job's unique window
     * (config tenancy.provisioning.unique_for): its dispatch was lost or its worker died, so
     * no job can still be working on it and a super-admin retry is allowed.
     *
     * Reads the real `tenants.updated_at` column (a custom column, see getCustomColumns()).
     */
    public function provisioningIsStale(int $windowSeconds): bool
    {
        if (! $this->provisioningStatus()->isInProgress()) {
            return false;
        }

        $started = $this->provisioning_started_at;
        $updated = $this->updated_at;
        $last = match (true) {
            $started === null => $updated,
            $updated === null => $started,
            default => $started->gt($updated) ? $started : $updated,
        };

        return $last === null || $last->lt(now()->subSeconds($windowSeconds));
    }

    // ========================================================================
    // بيانات اتصال قاعدة البيانات (Tenant database credentials)
    // ========================================================================

    /**
     * `tenancy_db_password` is stored ENCRYPTED in the central `data` column (W2 batch 4,
     * S-sec3) and read decrypted, so stancl's DatabaseConfig (getPassword(), tenantConfig()
     * both go through getAttribute()) builds the tenant connection with the real password.
     * Get-only on purpose: stancl's VirtualColumn re-assigns every `data` key through
     * setAttribute() when it decodes the row, so a set-mutator would encrypt twice. Writers
     * seal the value with sealDatabasePassword(). A legacy PLAINTEXT value (rows written
     * before this change) is returned as is.
     */
    protected function tenancyDbPassword(): Attribute
    {
        return Attribute::make(get: static fn (mixed $value): mixed => self::revealDatabasePassword($value));
    }

    /** Encrypt a tenant database password for `tenancy_db_password` (null / '' unchanged). */
    public static function sealDatabasePassword(#[SensitiveParameter] ?string $password): ?string
    {
        return $password === null || $password === '' ? $password : Crypt::encryptString($password);
    }

    private static function revealDatabasePassword(#[SensitiveParameter] mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value; // legacy plaintext row
        }
    }

    /**
     * Tenants whose database exists and is fully set up (`ready`). Every loop over "all
     * tenants" (tenants:* commands, backups, health, audits) must use it: a pending,
     * running or failed tenant has no usable database.
     *
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeProvisioned(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('provisioning_status'), TenantProvisioningStatus::Ready->value);
    }

    // ========================================================================
    // العلاقات (Relationships)
    // ========================================================================

    /**
     * Typed override of stancl's HasDomains::domains() (same query) so static analysis
     * can resolve the relation.
     *
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /**
     * Status change history (central, append-only), IDEN-3.2.
     *
     * @return HasMany<TenantLifecycleEvent, $this>
     */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(TenantLifecycleEvent::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('starts_at');
    }

    // ========================================================================
    // نظام الفيتشرز (Feature Flags System)
    // ========================================================================

    /**
     * التحقق من توفر فيتشر معين للمستأجر.
     * يتحقق أولاً من الفيتشرز المفعلة يدوياً (Override)، ثم من فيتشرز الباقة.
     */
    public function hasFeature(string $key): bool
    {
        // 1. التحقق من الفيتشرز المفعلة يدوياً (Override بواسطة Super Admin)
        $manualFeatures = $this->enabled_features ?? [];
        if (in_array($key, $manualFeatures, true)) {
            return true;
        }

        // 2. التحقق من فيتشرز الباقة الحالية
        $plan = $this->plan;
        if (! $plan) {
            return false;
        }

        $planFeatures = $plan->features ?? [];

        return isset($planFeatures[$key]) && $planFeatures[$key] === true;
    }

    /**
     * الحصول على قيمة حد (Limit) لفيتشر معين.
     * مثال: limits.users => 10
     */
    public function getFeatureLimit(string $key): int
    {
        $plan = $this->plan;
        if (! $plan) {
            return 0;
        }

        $features = $plan->features ?? [];

        return (int) ($features[$key] ?? 0);
    }

    /**
     * التحقق من أن المستأجر لم يتجاوز حد مورد معين.
     */
    public function checkLimit(string $resource): bool
    {
        return match ($resource) {
            'users' => User::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.users'),
            'stores' => Store::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.stores'),
            'items' => Item::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.items'),
            default => true,
        };
    }

    /**
     * الحصول على كافة الفيتشرز المتاحة للمستأجر (من الباقة + الفيتشرز اليدوية).
     * تُستخدم لمشاركتها مع الواجهة عبر الـ API.
     */
    public function getAllFeatures(): array
    {
        $planFeatures = $this->plan ? ($this->plan->features ?? []) : [];
        $manualFeatures = $this->enabled_features ?? [];

        // دمج الفيتشرز اليدوية مع فيتشرز الباقة
        foreach ($manualFeatures as $key) {
            $planFeatures[$key] = true;
        }

        return $planFeatures;
    }

    /**
     * الحصول على كافة حدود الاستخدام (null = غير محدود).
     *
     * @return array<string, int|null>
     */
    public function getAllLimits(): array
    {
        $plan = $this->plan;
        if (! $plan) {
            return [];
        }

        return [
            'users' => $plan->max_users,
            'stores' => $plan->max_stores,
            'warehouses' => $plan->max_warehouses,
            'vans' => $plan->max_vans,
            'items' => $plan->max_items,
            'invoices_month' => $plan->max_invoices_per_month,
            'storage_mb' => $plan->max_storage_mb,
        ];
    }

    // ========================================================================
    // دورة حياة المستأجر (Lifecycle, IDEN-3.2)
    // ========================================================================

    /** The stored status as an enum, or null for a value outside App\Enums\TenantStatus. */
    public function lifecycleStatus(): ?TenantStatus
    {
        return TenantStatus::tryFrom((string) $this->status);
    }

    /**
     * The stored lifecycle facts for App\Support\Tenancy\TenantLifecyclePolicy.
     *
     * An unknown stored status is read as `suspended` (never grants access by accident).
     * $hasEverPaid is supplied by the caller (billing knows it, ENTI-3.x).
     */
    public function lifecycleSnapshot(bool $hasEverPaid = false): TenantLifecycleSnapshot
    {
        return new TenantLifecycleSnapshot(
            status: $this->lifecycleStatus() ?? TenantStatus::Suspended,
            statusChangedAt: self::immutable($this->status_changed_at),
            trialEndsAt: self::immutable($this->trial_ends_at),
            subscriptionEndsAt: self::immutable($this->subscription_ends_at),
            graceEndsAt: self::immutable($this->grace_ends_at),
            trialExtendedAt: self::immutable($this->trial_extended_at),
            hasEverPaid: $hasEverPaid,
            suspensionReason: $this->suspension_reason,
        );
    }

    private static function immutable(?Carbon $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::instance($value);
    }

    // ========================================================================
    // حالة الاشتراك (Subscription Status)
    // ========================================================================

    /**
     * هل المستأجر في فترة التجربة المجانية؟
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * هل المستأجر نشط ومدفوع؟
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->subscription_ends_at
            && $this->subscription_ends_at->isFuture();
    }

    /**
     * هل المستأجر معلق أو منتهي الاشتراك؟
     *
     * Legacy gate of the workspace resolver until IDEN-3.5 (EnsureTenantActive) replaces it
     * with the derived lifecycle status. IDEN-3.2 keeps it at least as strict as before:
     *  - every blocked status (suspended / cancelled / archived) and any unknown value;
     *  - a trial whose trial_ends_at passed (trials no longer carry subscription_ends_at);
     *  - any tenant whose paid period ended (unchanged legacy rule).
     * read_only is NOT blocked here: reads stay allowed, writes get 423 from IDEN-3.5.
     */
    public function isSuspended(): bool
    {
        $status = $this->lifecycleStatus();

        if ($status === null || $status->accessLevel() === TenantAccessLevel::Blocked) {
            return true;
        }

        if ($status === TenantStatus::Trial && $this->trial_ends_at !== null && $this->trial_ends_at->isPast()) {
            return true;
        }

        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isPast();
    }

    /**
     * تفعيل فيتشر يدوياً (Override) بواسطة Super Admin.
     */
    public function enableFeature(string $key): void
    {
        $features = $this->enabled_features ?? [];
        if (! in_array($key, $features, true)) {
            $features[] = $key;
            $this->update(['enabled_features' => $features]);
        }
    }

    /**
     * تعطيل فيتشر يدوي.
     */
    public function disableFeature(string $key): void
    {
        $features = $this->enabled_features ?? [];
        $features = array_values(array_filter($features, fn ($f) => $f !== $key));
        $this->update(['enabled_features' => $features]);
    }
}
