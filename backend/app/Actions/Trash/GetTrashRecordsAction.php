<?php

declare(strict_types=1);

namespace App\Actions\Trash;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;

final class GetTrashRecordsAction
{
    /**
     * Get trashed records and counts for all modules
     */
    public function execute(string $tab = 'items', string $search = '', int $perPage = 15): array
    {
        $itemsCount = Item::onlyTrashed()->count();
        $customersCount = Customer::onlyTrashed()->count();
        $suppliersCount = Supplier::onlyTrashed()->count();
        $storesCount = Store::onlyTrashed()->count();
        $expensesCount = Expense::onlyTrashed()->count();
        $returnsCount = ReturnDocument::onlyTrashed()->count();

        $records = [];
        $pagination = [
            'current_page' => 1,
            'last_page'    => 1,
            'per_page'     => $perPage,
            'total'        => 0,
        ];

        if ($tab === 'items') {
            $q = Item::onlyTrashed();
            if ($search !== '') {
                $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($i) => [
                'id'         => $i->id,
                'title'      => $i->name,
                'subtitle'   => $i->code ?? '—',
                'category'   => $i->category,
                'deleted_at' => $i->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        } elseif ($tab === 'customers') {
            $q = Customer::onlyTrashed();
            if ($search !== '') {
                $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($c) => [
                'id'         => $c->id,
                'title'      => $c->name,
                'subtitle'   => $c->phone ?? '—',
                'deleted_at' => $c->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        } elseif ($tab === 'suppliers') {
            $q = Supplier::onlyTrashed();
            if ($search !== '') {
                $q->where(fn($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($s) => [
                'id'         => $s->id,
                'title'      => $s->name,
                'subtitle'   => $s->company_name ?? '—',
                'deleted_at' => $s->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        } elseif ($tab === 'stores') {
            $q = Store::onlyTrashed();
            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($st) => [
                'id'         => $st->id,
                'title'      => $st->name,
                'subtitle'   => $st->code ?? '—',
                'category'   => $st->type,
                'deleted_at' => $st->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        } elseif ($tab === 'expenses') {
            $q = Expense::onlyTrashed();
            if ($search !== '') {
                $q->where('title', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($e) => [
                'id'         => $e->id,
                'title'      => $e->title ?? 'مصروف',
                'subtitle'   => (string)$e->amount . ' ج.م',
                'category'   => $e->category,
                'deleted_at' => $e->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        } elseif ($tab === 'returns') {
            $q = ReturnDocument::onlyTrashed();
            if ($search !== '') {
                $q->where('return_number', 'like', "%{$search}%");
            }
            $paged = $q->latest('deleted_at')->paginate($perPage);
            $records = collect($paged->items())->map(fn($r) => [
                'id'         => $r->id,
                'title'      => $r->return_number,
                'subtitle'   => (string)$r->net_total . ' ج.م',
                'deleted_at' => $r->deleted_at?->diffForHumans(),
            ]);
            $pagination = [
                'current_page' => $paged->currentPage(),
                'last_page'    => $paged->lastPage(),
                'per_page'     => $paged->perPage(),
                'total'        => $paged->total(),
            ];
        }

        return [
            'tab'        => $tab,
            'records'    => $records,
            'counts'     => [
                'items'     => $itemsCount,
                'customers' => $customersCount,
                'suppliers' => $suppliersCount,
                'stores'    => $storesCount,
                'expenses'  => $expensesCount,
                'returns'   => $returnsCount,
            ],
            'pagination' => $pagination,
        ];
    }
}
