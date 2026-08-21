<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Logs\GetActivityLogsAction;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly GetActivityLogsAction $getActivityLogsAction
    ) {}

    /**
     * List system activity & audit logs
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->getActivityLogsAction->execute($request->all());

        $users = User::orderBy('name')->select('id', 'name', 'phone')->get();
        $stores = Store::where('is_active', true)->select('id', 'name')->get();

        $modulesList = [
            'sales'         => 'المبيعات و POS 🛒',
            'inventory'     => 'الأصناف والمخزون 📦',
            'shifts'        => 'الخزينة والورديات 💵',
            'purchases'     => 'المشتريات والتوريد 🚚',
            'expenses'      => 'المصروفات 💸',
            'contacts'      => 'العملاء والموردين 👥',
            'auth'          => 'الأمان والدخول 🔐',
            'settings'      => 'إدارة النظام والإعدادات ⚙️',
            'transfers'     => 'التحويلات المخزنية 🔄',
            'blends'        => 'توليفات البن ☕',
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
}
