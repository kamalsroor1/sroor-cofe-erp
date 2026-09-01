<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Subscription;

class SuperAdminAnalyticsService implements SuperAdminDashboardAnalyticsInterface
{
    /**
     * حساب وتحليل مؤشرات أداء المنصة المركزية بدقة مالية تامة
     */
    public function getPlatformMetrics(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        // Calculate MRR (Monthly Recurring Revenue) with bcmath
        $monthlyRevenue = (string)(Subscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount') ?: '0.000');

        $yearlyRevenue = (string)(Subscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount') ?: '0.000');

        $yearlyPortion = bcdiv($yearlyRevenue, '12', 3);
        $mrr = bcadd($monthlyRevenue, $yearlyPortion, 2);

        return [
            'total_tenants'     => $totalTenants,
            'active_tenants'    => $activeTenants,
            'trial_tenants'     => $trialTenants,
            'suspended_tenants' => $suspendedTenants,
            'mrr'               => (float)$mrr,
        ];
    }

    /**
     * إحصائيات توزيع الباقات
     */
    public function getPlanStatistics(): array
    {
        return Plan::withCount('tenants')->get()->map(fn($p) => [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'price_monthly' => (float)$p->price_monthly,
            'tenants_count' => $p->tenants_count,
        ])->toArray();
    }

    /**
     * أحدث المستأجرين المسجلين
     */
    public function getRecentTenants(int $limit = 5): array
    {
        $centralDomain = config('tenancy.central_domains.0', request()->getHost() ?: 'baraa-solutions.com');

        return Tenant::with(['plan', 'domains'])
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'slug'       => $t->slug,
                'domain'     => $t->domains->first()?->domain ?? ($t->slug . '.' . $centralDomain),
                'plan_name'  => $t->plan?->name ?? __('common.unspecified', [], 'ar') ?: 'غير محدد',
                'status'     => $t->status,
                'created_at' => $t->created_at->diffForHumans(),
            ])
            ->toArray();
    }
}
