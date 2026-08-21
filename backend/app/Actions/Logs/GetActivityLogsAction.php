<?php

declare(strict_types=1);

namespace App\Actions\Logs;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetActivityLogsAction
{
    /**
     * Fetch paginated and filtered activity logs
     */
    public function execute(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $module = (string)($filters['module'] ?? 'all');
        $action = (string)($filters['action'] ?? 'all');
        $userId = $filters['user_id'] ?? 'all';
        $storeId = $filters['store_id'] ?? 'all';
        $fromDate = $filters['from_date'] ?? $filters['from'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to'] ?? null;
        $perPage = (int)($filters['per_page'] ?? 25);

        $query = ActivityLog::with(['user', 'store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                  ->orWhereHas('store', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($module !== 'all' && $module !== '') {
            $query->where('module', $module);
        }

        if ($action !== 'all' && $action !== '') {
            $query->where('action', $action);
        }

        if ($userId !== 'all' && $userId !== null && $userId !== '') {
            $query->where('user_id', (int)$userId);
        }

        if ($storeId !== 'all' && $storeId !== null && $storeId !== '') {
            $query->where('store_id', (int)$storeId);
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $totalCount = (int)(clone $query)->count();
        $logs = $query->latest('id')->paginate($perPage);

        // Stats
        $todayLogs = ActivityLog::whereDate('created_at', now()->toDateString());
        $stats = [
            'today_total'    => (int)$todayLogs->count(),
            'today_critical' => (int)(clone $todayLogs)->whereIn('action', ['cancelled', 'deleted', 'login_failed'])->count(),
            'today_users'    => (int)(clone $todayLogs)->distinct('user_id')->count('user_id'),
            'today_stores'   => (int)(clone $todayLogs)->distinct('store_id')->count('store_id'),
        ];

        $formattedLogs = collect($logs->items())->map(function (ActivityLog $log) {
            $badge = $log->module_badge;
            return [
                'id'           => $log->id,
                'module'       => $log->module,
                'module_label' => $badge['label'] ?? $log->module,
                'module_color' => $badge['color'] ?? 'slate',
                'module_icon'  => $badge['icon'] ?? '⚙️',
                'action'       => $log->action,
                'description'  => $log->description,
                'properties'   => $log->properties,
                'user_name'    => $log->user?->name ?? 'النظام التلقائي',
                'user_phone'   => $log->user?->phone,
                'store_name'   => $log->store?->name ?? 'الفرع الرئيسي',
                'ip_address'   => $log->ip_address,
                'user_agent'   => $log->user_agent,
                'payload'      => $log->payload,
                'created_at'   => $log->created_at?->format('Y-m-d H:i:s'),
                'time_ago'     => $log->created_at?->diffForHumans(),
            ];
        });

        return [
            'logs'        => $formattedLogs,
            'stats'       => $stats,
            'total_count' => $totalCount,
            'pagination'  => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ];
    }
}
