<?php

declare(strict_types=1);

namespace App\Services\Shifts;

use App\Models\CashShift;
use App\Services\ShiftService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Collection;

/**
 * SHIFT-02: the cash drawers of a branch (or of every branch) = its open shifts, for the readers
 * that show "the drawer" (daily journal + A4 print, treasury summary, dashboards, treasury
 * balances).
 *
 * One drawer per open shift, keyed exactly like TreasuryService always did:
 *  - shared shift: 'store:{id}' - one drawer per branch (the later shift wins; the open_guard
 *    allows only one anyway);
 *  - per-cashier shift: 'shift:{id}' - every cashier's open shift is its own drawer, so the
 *    branch figures are the SUM over them (a per-cashier branch with two cashiers open, floats
 *    500 + 200, has 700 of opening cash, never just the newest cashier's 200).
 *
 * In a shared branch the sums are over one shift, so every figure is the one it was before.
 * Money is scale-3 bcmath strings.
 */
final class BranchDrawers
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly TenantClock $tenantClock,
    ) {}

    public static function drawerKey(CashShift $shift): string
    {
        return $shift->isPerCashier() ? 'shift:'.$shift->id : 'store:'.(int) $shift->store_id;
    }

    /**
     * The open shifts that are drawers of $storeId (null = every branch), oldest first.
     *
     * @param  list<string>  $with
     * @return Collection<string, CashShift>
     */
    public static function open(?int $storeId, array $with = []): Collection
    {
        return CashShift::query()
            ->with($with)
            ->where('status', 'open')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('id')
            ->get()
            ->keyBy(fn (CashShift $shift): string => self::drawerKey($shift));
    }

    /**
     * Sum of the opening floats of the open drawers of $storeId (null = every branch).
     */
    public static function openingCash(?int $storeId): string
    {
        $total = '0.000';
        foreach (self::open($storeId) as $shift) {
            $total = bcadd($total, Decimal::normalize((string) $shift->opening_cash_balance), 3);
        }

        return $total;
    }

    /**
     * Expected cash over the open drawers of $storeId (ShiftService::calculateShiftTotals(), the
     * figure of GET /shifts/current and of each provisional Z), null when no drawer is open.
     */
    public function expectedCash(?int $storeId): ?string
    {
        return $this->summary($storeId)['expected_cash_balance'];
    }

    /**
     * The open drawers of $storeId with their branch totals.
     *
     * @return array{count: int, opening_cash_balance: string, expected_cash_balance: string|null, shifts: list<array{id: int, shift_number: string, shift_mode: string, store_id: int|null, user_id: int|null, user_name: string|null, opened_at: string|null, opening_cash_balance: string, expected_cash_balance: string}>}
     */
    public function summary(?int $storeId): array
    {
        $opening = '0.000';
        $expected = '0.000';
        $rows = [];

        foreach (self::open($storeId, ['user:id,name']) as $shift) {
            $float = Decimal::normalize((string) $shift->opening_cash_balance);
            $drawer = $this->shiftService->calculateShiftTotals($shift)['expected_cash_balance'];
            $opening = bcadd($opening, $float, 3);
            $expected = bcadd($expected, $drawer, 3);

            $rows[] = [
                'id' => (int) $shift->id,
                'shift_number' => (string) $shift->shift_number,
                'shift_mode' => (string) $shift->shift_mode,
                'store_id' => $shift->store_id !== null ? (int) $shift->store_id : null,
                'user_id' => $shift->user_id !== null ? (int) $shift->user_id : null,
                'user_name' => $shift->user?->name,
                'opened_at' => $this->tenantClock->toTenant($shift->opened_at)?->format('Y-m-d H:i'),
                'opening_cash_balance' => $float,
                'expected_cash_balance' => $drawer,
            ];
        }

        return [
            'count' => count($rows),
            'opening_cash_balance' => $opening,
            'expected_cash_balance' => $rows === [] ? null : $expected,
            'shifts' => $rows,
        ];
    }

    /**
     * DATE-03 + SHIFT-02: the opening float of the business date $date in $storeId (null = every
     * branch). Today: the floats of the open drawers. An older date, per drawer (a shared branch;
     * a per-cashier branch's cashier): the float of the first shift opened on that date, else of
     * the shift already open when that day started - never today's open shift for a past day.
     * The drawers' floats are summed.
     *
     * $perCashierOnly limits the past-date sum to per-cashier shifts (the A4 print keeps its own
     * shared-shift rule).
     */
    public function openingCashFor(string $date, ?int $storeId, bool $perCashierOnly = false): string
    {
        if ($date === $this->tenantClock->today()) {
            $total = '0.000';
            foreach (self::open($storeId) as $shift) {
                if (! $perCashierOnly || $shift->isPerCashier()) {
                    $total = bcadd($total, Decimal::normalize((string) $shift->opening_cash_balance), 3);
                }
            }

            return $total;
        }

        [$start, $end] = $this->tenantClock->businessDayRange($date);

        $shifts = fn () => CashShift::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->when($perCashierOnly, fn ($q) => $q->where('shift_mode', 'per_cashier'))
            ->orderBy('opened_at')
            ->orderBy('id');
        $columns = ['id', 'store_id', 'user_id', 'shift_mode', 'opened_at', 'opening_cash_balance'];

        /** @var array<string, CashShift> $openedThatDay first shift opened inside the day, per drawer */
        $openedThatDay = [];
        foreach ($shifts()->where('opened_at', '>=', $start)->where('opened_at', '<', $end)->get($columns) as $shift) {
            $openedThatDay[$this->dayDrawerKey($shift)] ??= $shift;
        }

        /** @var array<string, CashShift> $openAtStart latest shift already open when the day started, per drawer */
        $openAtStart = [];
        $alreadyOpen = $shifts()
            ->where('opened_at', '<', $start)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $start))
            ->get($columns);
        foreach ($alreadyOpen as $shift) {
            $openAtStart[$this->dayDrawerKey($shift)] = $shift; // ascending order: the latest one stays
        }

        $total = '0.000';
        foreach ($openedThatDay + $openAtStart as $shift) {
            $total = bcadd($total, Decimal::normalize((string) $shift->opening_cash_balance), 3);
        }

        return $total;
    }

    /**
     * A drawer across days: a shared branch, or one cashier of a per-cashier branch.
     */
    private function dayDrawerKey(CashShift $shift): string
    {
        return $shift->isPerCashier()
            ? 'store:'.(int) $shift->store_id.':user:'.(int) $shift->user_id
            : 'store:'.(int) $shift->store_id;
    }
}
