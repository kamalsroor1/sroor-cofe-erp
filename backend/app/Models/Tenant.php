<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'settings' => 'array',
        'enabled_features' => 'array',
        'features' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
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
        ];
    }

    // ========================================================================
    // العلاقات (Relationships)
    // ========================================================================

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
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
        if (!$plan) {
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
        if (!$plan) {
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
            'users'  => User::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.users'),
            'stores' => Store::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.stores'),
            'items'  => Item::where('tenant_id', $this->id)->count() < $this->getFeatureLimit('limits.items'),
            default  => true,
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
     * الحصول على كافة حدود الاستخدام.
     */
    public function getAllLimits(): array
    {
        $plan = $this->plan;
        if (!$plan) {
            return [];
        }

        return [
            'users'             => $plan->max_users,
            'stores'            => $plan->max_stores,
            'items'             => $plan->max_items,
            'invoices_month'    => $plan->max_invoices_per_month,
            'storage_mb'        => $plan->max_storage_mb,
        ];
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
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended'
            || ($this->subscription_ends_at && $this->subscription_ends_at->isPast());
    }

    /**
     * تفعيل فيتشر يدوياً (Override) بواسطة Super Admin.
     */
    public function enableFeature(string $key): void
    {
        $features = $this->enabled_features ?? [];
        if (!in_array($key, $features, true)) {
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
        $features = array_values(array_filter($features, fn($f) => $f !== $key));
        $this->update(['enabled_features' => $features]);
    }
}
