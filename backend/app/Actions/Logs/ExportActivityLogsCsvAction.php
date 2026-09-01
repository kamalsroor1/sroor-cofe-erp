<?php

declare(strict_types=1);

namespace App\Actions\Logs;

use App\Models\ActivityLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportActivityLogsCsvAction
{
    /**
     * Stream CSV export for filtered activity logs.
     */
    public function execute(array $filters): StreamedResponse
    {
        $search = trim((string)($filters['search'] ?? ''));
        $module = (string)($filters['module'] ?? 'all');
        $action = (string)($filters['action'] ?? 'all');
        $userId = $filters['user_id'] ?? 'all';
        $storeId = $filters['store_id'] ?? 'all';
        $fromDate = $filters['from_date'] ?? $filters['from'] ?? null;
        $toDate = $filters['to_date'] ?? $filters['to'] ?? null;

        $query = ActivityLog::with(['user:id,name,phone', 'store:id,name']);

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

        $filename = 'activity_logs_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'ID',
                __('inventory.movement_date_time') ?: 'Date/Time',
                __('common.user') ?: 'User',
                __('common.phone') ?: 'Phone',
                __('common.store') ?: 'Store',
                __('inventory.category') ?: 'Module',
                __('inventory.movement_type') ?: 'Action',
                __('inventory.notes_and_statement') ?: 'Description',
                'IP Address',
            ]);

            $query->orderBy('created_at', 'desc')->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user?->name ?? __('common.system'),
                        $log->user?->phone ?? '-',
                        $log->store?->name ?? __('common.main_store_default'),
                        $log->module,
                        $log->action,
                        $log->description,
                        $log->ip_address ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
