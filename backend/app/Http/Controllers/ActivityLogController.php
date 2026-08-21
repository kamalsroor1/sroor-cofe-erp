<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string)$request->input('search', ''));
        $module = $request->input('module', 'all');
        $action = $request->input('action', 'all');
        $userId = $request->input('user_id', 'all');
        $storeId = $request->input('store_id', 'all');
        $datePreset = $request->input('preset', 'all');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');
        $viewMode = $request->input('view', 'timeline');

        if (!$dateFrom && !$dateTo) {
            if ($datePreset === 'today') {
                $dateFrom = now()->toDateString();
                $dateTo = now()->toDateString();
            } elseif ($datePreset === 'yesterday') {
                $dateFrom = now()->subDay()->toDateString();
                $dateTo = now()->subDay()->toDateString();
            } elseif ($datePreset === '7days') {
                $dateFrom = now()->subDays(6)->toDateString();
                $dateTo = now()->toDateString();
            } elseif ($datePreset === '30days') {
                $dateFrom = now()->subDays(29)->toDateString();
                $dateTo = now()->toDateString();
            }
        }

        $query = ActivityLog::with(['user', 'store']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                  ->orWhereHas('store', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($module !== 'all') {
            $query->where('module', $module);
        }

        if ($action !== 'all') {
            $query->where('action', $action);
        }

        if ($userId !== 'all') {
            $query->where('user_id', (int)$userId);
        }

        if ($storeId !== 'all') {
            $query->where('store_id', (int)$storeId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->latest('id')->paginate(25)->withQueryString();

        // 4 KPI Summary Stats
        $todayLogs = ActivityLog::whereDate('created_at', now()->toDateString());
        $stats = [
            'today_total' => $todayLogs->count(),
            'today_critical' => (clone $todayLogs)->whereIn('action', ['cancelled', 'deleted', 'login_failed'])->count(),
            'today_users' => (clone $todayLogs)->distinct('user_id')->count('user_id'),
            'today_stores' => (clone $todayLogs)->distinct('store_id')->count('store_id'),
        ];

        $users = User::orderBy('name')->select('id', 'name', 'phone')->get();
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        $modulesList = [
            'sales'      => 'المبيعات و POS 🛒',
            'inventory'  => 'الأصناف والمخزون 📦',
            'shifts'     => 'الخزينة والورديات 💵',
            'purchases'  => 'المشتريات والتوريد 🚚',
            'expenses'   => 'المصروفات 💸',
            'contacts'   => 'العملاء والموردين 👥',
            'auth'       => 'الأمان والدخول 🔐',
            'settings'   => 'إدارة النظام والإعدادات ⚙️',
        ];

        return Inertia::render('ActivityLogs/Index', [
            'users' => $users,
            'stores' => $stores,
            'modules_list' => $modulesList,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'user_id' => $userId,
                'store_id' => $storeId,
                'preset' => $datePreset,
                'from' => $dateFrom,
                'to' => $dateTo,
                'view' => $viewMode,
            ],
            'logs' => Inertia::defer(fn() => $logs->through(fn($log) => [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'description' => $log->description,
                'user_name' => $log->user?->name ?: 'النظام / تلقائي',
                'user_phone' => $log->user?->phone,
                'store_name' => $log->store?->name ?: 'الفرع الرئيسي',
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'payload' => $log->payload,
                'created_at' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                'time_ago' => $log->created_at ? $log->created_at->diffForHumans() : '',
            ]), 'activityLogsData'),
            'stats' => Inertia::defer(fn() => $stats, 'activityLogsData'),
        ]);
    }

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
                'المعرف',
                'التاريخ والوقت',
                'الموظف المسؤول',
                'رقم الهاتف',
                'الفرع / المخزن',
                'القسم',
                'نوع الإجراء',
                'الوصف والتفاصيل',
                'عنوان IP',
            ]);

            $query->orderBy('created_at', 'desc')->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->name ?? 'النظام',
                        $log->user?->phone ?? '-',
                        $log->store?->name ?? 'الفرع الرئيسي',
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