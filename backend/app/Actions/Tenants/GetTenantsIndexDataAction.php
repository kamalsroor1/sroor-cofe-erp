<?php

namespace App\Actions\Tenants;

use App\Filters\Tenants\PlanFilter;
use App\Filters\Tenants\SearchFilter;
use App\Filters\Tenants\StatusFilter;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class GetTenantsIndexDataAction
{
    /**
     * جلب وترشيح قائمة المستأجرين عبر Pipeline و JsonResources
     */
    public function execute(Request $request): array
    {
        $tenantsQuery = app(Pipeline::class)
            ->send(Tenant::query()->with(['plan', 'domains', 'subscriptions' => fn ($q) => $q->latest()->take(1)]))
            ->through([
                SearchFilter::class,
                StatusFilter::class,
                PlanFilter::class,
            ])
            ->thenReturn();

        $tenants = $tenantsQuery->latest()->paginate(15)->withQueryString()->through(fn ($t) => (new TenantResource($t))->resolve());

        $plans = PlanResource::collection(Plan::select('id', 'name', 'slug')->get())->resolve();

        return [
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', 'all'),
                'plan_id' => $request->query('plan_id', 'all'),
            ],
        ];
    }
}
