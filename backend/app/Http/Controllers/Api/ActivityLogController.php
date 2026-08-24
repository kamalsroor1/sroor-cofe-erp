<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Logs\ExportActivityLogsCsvAction;
use App\Actions\Logs\GetActivityLogsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterActivityLogsRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly GetActivityLogsAction $getActivityLogsAction,
        private readonly ExportActivityLogsCsvAction $exportActivityLogsCsvAction
    ) {}

    /**
     * List system activity & audit logs
     */
    public function index(FilterActivityLogsRequest $request): JsonResponse
    {
        $result = $this->getActivityLogsAction->execute($request->validated());

        $users = User::orderBy('name')->select(['id', 'name', 'phone'])->get();
        $stores = Store::where('is_active', true)->select(['id', 'name'])->get();

        $modulesList = [
            'sales'     => __('dashboard.modules_sales') ?: 'المبيعات و POS 🛒',
            'inventory' => __('dashboard.modules_inventory') ?: 'الأصناف والمخزون 📦',
            'shifts'    => __('dashboard.modules_shifts') ?: 'الخزينة والورديات 💵',
            'purchases' => __('dashboard.modules_purchases') ?: 'المشتريات والتوريد 🚚',
            'expenses'  => __('dashboard.modules_expenses') ?: 'المصروفات 💸',
            'contacts'  => __('dashboard.modules_contacts') ?: 'العملاء والموردين 👥',
            'auth'      => __('dashboard.modules_auth') ?: 'الأمان والدخول 🔐',
            'settings'  => __('dashboard.modules_settings') ?: 'إدارة النظام والإعدادات ⚙️',
            'transfers' => __('dashboard.modules_transfers') ?: 'التحويلات المخزنية 🔄',
            'blends'    => __('dashboard.modules_blends') ?: 'تجميع وتوليف الأصناف 🔄',
        ];

        return response()->json([
            'success'      => true,
            'data'         => $result['logs'],
            'stats'        => $result['stats'],
            'total_count'  => $result['total_count'],
            'pagination'   => $result['pagination'],
            'users'        => $users,
            'stores'       => $stores,
            'modules_list' => $modulesList,
        ]);
    }

    /**
     * Export activity logs as UTF-8 CSV
     */
    public function exportCsv(FilterActivityLogsRequest $request): StreamedResponse
    {
        return $this->exportActivityLogsCsvAction->execute($request->validated());
    }
}
