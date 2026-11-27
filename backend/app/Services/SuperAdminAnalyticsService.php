<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\PlatformHosts;

class SuperAdminAnalyticsService implements SuperAdminDashboardAnalyticsInterface
{
    /**
     * حساب وتحليل مؤشرات أداء المنصة المركزية بدقة مالية تامة
     */
    public function getPlatformMetrics(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $freePlanTenants = Tenant::whereHas('plan', fn ($q) => $q->where('is_free', true))->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        // Calculate MRR (Monthly Recurring Revenue) with bcmath
        $monthlyRevenue = (string) (Subscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount') ?: '0.000');

        $yearlyRevenue = (string) (Subscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount') ?: '0.000');

        $yearlyPortion = bcdiv($yearlyRevenue, '12', 3);
        $mrr = bcadd($monthlyRevenue, $yearlyPortion, 2);

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'free_plan_tenants' => $freePlanTenants,
            'suspended_tenants' => $suspendedTenants,
            'mrr' => (float) $mrr,
        ];
    }

    /**
     * إحصائيات توزيع الباقات
     */
    public function getPlanStatistics(): array
    {
        return Plan::withCount('tenants')->orderBy('sort_order')->orderBy('id')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->localizedName(),
            'slug' => $p->slug,
            'price_monthly' => (float) $p->price_monthly,
            'tenants_count' => $p->tenants_count,
        ])->toArray();
    }

    /**
     * أحدث المستأجرين المسجلين
     */
    public function getRecentTenants(int $limit = 5): array
    {
        return Tenant::with(['plan', 'domains'])
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'domain' => $t->domains->first()?->domain ?? PlatformHosts::tenantHost((string) $t->slug),
                'plan_name' => $t->plan?->localizedName() ?? __('common.unspecified'),
                'status' => $t->status,
                'created_at' => $t->created_at->diffForHumans(),
            ])
            ->toArray();
    }
}
