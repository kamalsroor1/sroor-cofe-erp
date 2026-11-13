<?php

declare(strict_types=1);

namespace App\Actions\Logs;

use App\DTOs\Logs\ActivityLogStoreScope;
use App\Filters\ActivityLogs\ReadableStoresFilter;
use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantClock;

final class GetActivityLogsAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * Fetch paginated and filtered activity logs
     */
    public function execute(array $filters, ActivityLogStoreScope $scope): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $module = (string) ($filters['module'] ?? 'all');
        $action = (string) ($filters['action'] ?? 'all');
        $userId = $filters['user_id'] ?? 'all';
        $fromDate = $filters['from_date'] ?? $filters['from'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to'] ?? null;
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 25)));

        $query = ActivityLog::select([
            'id',
            'user_id',
            'store_id',
            'module',
            'action',
            'description',
            'ip_address',
            'user_agent',
            'properties',
            'created_at',
        ])->with([
            'user:id,name,phone',
            'store:id,name',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('store', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($module !== 'all' && $module !== '') {
            $query->where('module', $module);
        }

        if ($action !== 'all' && $action !== '') {
            $query->where('action', $action);
        }

        if ($userId !== 'all' && $userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        // LOG-01: only the branches the reader may see (and the access-checked store_id filter).
        $storesFilter = new ReadableStoresFilter($scope);
        $storesFilter->apply($query);

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $totalCount = (int) (clone $query)->count();
        $logs = $query->latest('id')->paginate($perPage);

        // Optimized Stats
        // SETG-2 ext: "today" is the tenant business day, as a [start, end) timestamp range.
        [$dayStart, $dayEnd] = $this->tenantClock->businessDayRange($this->tenantClock->businessDate());
        // Stats ignore the store_id filter (as before) but never count a branch the reader cannot see.
        $todayLogs = (new ReadableStoresFilter(new ActivityLogStoreScope($scope->readableStoreIds)))->apply(
            ActivityLog::where('created_at', '>=', $dayStart)->where('created_at', '<', $dayEnd)
        );
        $stats = [
            'today_total' => (int) (clone $todayLogs)->count(),
            'today_critical' => (int) (clone $todayLogs)->whereIn('action', ['cancelled', 'deleted', 'login_failed'])->count(),
            'today_users' => (int) (clone $todayLogs)->distinct('user_id')->count('user_id'),
            'today_stores' => (int) (clone $todayLogs)->distinct('store_id')->count('store_id'),
        ];

        $formattedLogs = collect($logs->items())->map(function (ActivityLog $log) {
            $badge = $log->module_badge;

            return [
                'id' => $log->id,
                'module' => $log->module,
                'module_label' => $badge['label'] ?? $log->module,
                'module_color' => $badge['color'] ?? 'slate',
                'module_icon' => $badge['icon'] ?? 'sliders',
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'user_name' => $log->user?->name ?? __('common.system'),
                'user_phone' => $log->user?->phone,
                'store_name' => $log->store?->name ?? __('common.main_store_default'),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'payload' => $log->properties,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'time_ago' => $log->created_at?->diffForHumans(),
            ];
        });

        // LOG-01: the branch filter lists only the branches the reader may see.
        $stores = Store::query()
            ->where('is_active', true)
            ->when(! $scope->seesEveryStore(), fn ($q) => $q->whereIn('id', $scope->readableStoreIds ?? []))
            ->select(['id', 'name'])
            ->get();

        // LOG-01: the user filter lists only people of the readable branches (default or assigned
        // branch), and never their phone numbers.
        $readable = $scope->readableStoreIds ?? [];
        $users = User::query()
            ->when(! $scope->seesEveryStore(), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('default_store_id', $readable)
                ->orWhereHas('stores', fn ($sq) => $sq->whereIn('stores.id', $readable))))
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get();

        return [
            'logs' => $formattedLogs,
            'stores' => $stores,
            'users' => $users,
            'stats' => $stats,
            'total_count' => $totalCount,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ];
    }
}
