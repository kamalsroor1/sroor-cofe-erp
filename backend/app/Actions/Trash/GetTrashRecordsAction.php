<?php

declare(strict_types=1);

namespace App\Actions\Trash;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use App\Support\Trash\TrashScope;

final class GetTrashRecordsAction
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Get trashed records and counts for all modules.
     *
     * SEC-21: every list and count goes through TrashScope (branch documents of readable stores
     * only, deleted branches for store managers only).
     */
    public function execute(User $actor, string $tab = 'items', string $search = '', int $perPage = 15): array
    {
        $currency = $this->tenantSettings->currencyLabel();

        $itemsCount = TrashScope::scope(Item::onlyTrashed(), $actor)->count();
        $customersCount = TrashScope::scope(Customer::onlyTrashed(), $actor)->count();
        $suppliersCount = TrashScope::scope(Supplier::onlyTrashed(), $actor)->count();
        $storesCount = TrashScope::scope(Store::onlyTrashed(), $actor)->count();
        $expensesCount = TrashScope::scope(Expense::onlyTrashed(), $actor)->count();
        $returnsCount = TrashScope::scope(ReturnDocument::onlyTrashed(), $actor)->count();

        $records = [];
        $pagination = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];

        if ($tab === 'items') {
            $q = TrashScope::scope(Item::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->name,
                'subtitle' => $i->code ?? '—',
                'category' => $i->category,
                'deleted_at' => $i->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        } elseif ($tab === 'customers') {
            $q = TrashScope::scope(Customer::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->phone ?? '—',
                'deleted_at' => $c->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        } elseif ($tab === 'suppliers') {
            $q = TrashScope::scope(Supplier::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->name,
                'subtitle' => $s->company_name ?? '—',
                'deleted_at' => $s->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        } elseif ($tab === 'stores') {
            $q = TrashScope::scope(Store::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($st) => [
                'id' => $st->id,
                'title' => $st->name,
                'subtitle' => $st->code ?? '—',
                'category' => $st->type,
                'deleted_at' => $st->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        } elseif ($tab === 'expenses') {
            $q = TrashScope::scope(Expense::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where('title', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title ?? __('expenses.expense_item'),
                'subtitle' => (string) $e->amount.' '.$currency,
                'category' => $e->category,
                'deleted_at' => $e->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        } elseif ($tab === 'returns') {
            $q = TrashScope::scope(ReturnDocument::onlyTrashed(), $actor);
            if ($search !== '') {
                $q->where('return_number', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->return_number,
                'subtitle' => (string) $r->total_amount.' '.$currency,
                // BE-02: deleting a return reversed its stock and balance; it cannot be restored.
                'restorable' => false,
                'deleted_at' => $r->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page' => $paged->lastPage(),
                'per_page' => $paged->perPage(),
                'total' => $paged->total(),
            ];
        }

        return [
            'tab' => $tab,
            'records' => $records,
            'counts' => [
                'items' => $itemsCount,
                'customers' => $customersCount,
                'suppliers' => $suppliersCount,
                'stores' => $storesCount,
                'expenses' => $expensesCount,
                'returns' => $returnsCount,
            ],
            'pagination' => $pagination,
        ];
    }
}
