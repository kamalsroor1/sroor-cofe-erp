<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Store;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('سجل العمليات والرقابة الذاتية | منظومة ERP')]
class ActivityLogIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $module = '';
    public string $action = '';
    public string $userId = '';
    public string $storeId = '';
    public string $datePreset = 'all'; // all, today, yesterday, 7days, 30days, custom
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $viewMode = 'timeline'; // timeline or table
    public ?int $selectedLogId = null;

    protected $queryString = [
        'search'     => ['except' => ''],
        'module'     => ['except' => ''],
        'action'     => ['except' => ''],
        'userId'     => ['except' => ''],
        'storeId'    => ['except' => ''],
        'datePreset' => ['except' => 'all'],
    ];

    public function mount()
    {
        abort_if(!auth()->user()?->can('logs.view'), 403, 'عفواً، لا تملك صلاحية عرض سجل العمليات والرقابة.');
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingModule() { $this->resetPage(); }
    public function updatingAction() { $this->resetPage(); }
    public function updatingUserId() { $this->resetPage(); }
    public function updatingStoreId() { $this->resetPage(); }
    public function updatingDatePreset() { $this->resetPage(); }

    public function setDatePreset(string $preset)
    {
        $this->datePreset = $preset;
        $this->resetPage();

        if ($preset === 'today') {
            $this->dateFrom = now()->toDateString();
            $this->dateTo = now()->toDateString();
        } elseif ($preset === 'yesterday') {
            $this->dateFrom = now()->subDay()->toDateString();
            $this->dateTo = now()->subDay()->toDateString();
        } elseif ($preset === '7days') {
            $this->dateFrom = now()->subDays(6)->toDateString();
            $this->dateTo = now()->toDateString();
        } elseif ($preset === '30days') {
            $this->dateFrom = now()->subDays(29)->toDateString();
            $this->dateTo = now()->toDateString();
        } elseif ($preset === 'all') {
            $this->dateFrom = '';
            $this->dateTo = '';
        }
    }

    public function resetFilters()
    {
        $this->reset(['search', 'module', 'action', 'userId', 'storeId', 'datePreset', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function showDetails(int $logId)
    {
        $this->selectedLogId = $logId;
    }

    public function closeDetails()
    {
        $this->selectedLogId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $query = $this->buildQuery();
        $filename = 'activity_logs_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel Arabic support
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

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

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->name ?? 'النظام',
                        $log->user?->phone ?? '-',
                        $log->store?->name ?? 'الفرع الرئيسي',
                        $log->module_badge['label'] ?? $log->module,
                        $log->action_badge['label'] ?? $log->action,
                        $log->description,
                        $log->ip_address ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function buildQuery()
    {
        return ActivityLog::with(['user', 'store'])
            ->when($this->search, function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('description', 'like', $term)
                        ->orWhere('action', 'like', $term)
                        ->orWhereHas('user', fn($u) => $u->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('store', fn($s) => $s->where('name', 'like', $term));
                });
            })
            ->when($this->module, fn($q) => $q->where('module', $this->module))
            ->when($this->action, fn($q) => $q->where('action', $this->action))
            ->when($this->userId, fn($q) => $q->where('user_id', $this->userId))
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy('created_at', 'desc');
    }

    public function render()
    {
        $logs = $this->buildQuery()->paginate(25);

        // Stats calculation
        $todayLogs = ActivityLog::whereDate('created_at', now()->toDateString());
        $stats = [
            'today_total'      => $todayLogs->count(),
            'today_critical'   => (clone $todayLogs)->whereIn('action', ['cancelled', 'deleted', 'login_failed'])->count(),
            'today_users'      => (clone $todayLogs)->distinct('user_id')->count('user_id'),
            'today_stores'     => (clone $todayLogs)->distinct('store_id')->count('store_id'),
        ];

        $users = User::orderBy('name')->get();
        $stores = Store::orderBy('name')->get();
        $selectedLog = $this->selectedLogId ? ActivityLog::with(['user', 'store'])->find($this->selectedLogId) : null;

        return view('livewire.activity-log-index', [
            'logs'        => $logs,
            'stats'       => $stats,
            'users'       => $users,
            'stores'      => $stores,
            'selectedLog' => $selectedLog,
        ]);
    }
}
