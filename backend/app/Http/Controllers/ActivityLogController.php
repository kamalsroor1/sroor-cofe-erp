<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityLogController extends Controller
{
    public function exportCsv(Request $request): StreamedResponse
    {
        $search = trim((string)$request->input('search', ''));
        $module = $request->input('module', 'all');
        $action = $request->input('action', 'all');
        $userId = $request->input('user_id', 'all');
        $storeId = $request->input('store_id', 'all');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $query = ActivityLog::with(['user', 'store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($module !== 'all') $query->where('module', $module);
        if ($action !== 'all') $query->where('action', $action);
        if ($userId !== 'all') $query->where('user_id', (int)$userId);
        if ($storeId !== 'all') $query->where('store_id', (int)$storeId);
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        $filename = 'activity_logs_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'ID',
                __('inventory.movement_date_time'),
                __('common.user'),
                __('common.phone'),
                __('common.store'),
                __('inventory.category'),
                __('inventory.movement_type'),
                __('inventory.notes_and_statement'),
                'IP Address',
            ]);

            $query->orderBy('created_at', 'desc')->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
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