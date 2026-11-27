<?php

namespace App\Services;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Actions\Invoices\ReverseInvoicePaymentsAction;
use App\Enums\LimitResource;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerCreditLimitGuard;
use App\Services\Entitlements\MonthlyInvoiceCounter;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Invoices\InvoiceCancelGuard;
use App\Services\Pos\SaleShiftGuard;
use App\Services\Pricing\SaleDiscountGuard;
use App\Services\Pricing\SalePriceGuard;
use App\Services\Settings\TenantSettings;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerBalanceService $customerBalanceService,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService,
        protected TenantClock $tenantClock,
        protected PlanEnforcer $planEnforcer,
        protected MonthlyInvoiceCounter $monthlyInvoiceCounter,
    ) {}

    /**
     * The user a document is attributed to: the authenticated user, else the explicit
     * `user_id` a job/command passed in $data, else NULL ("system"). Never a made-up id.
     *
     * @param  array<string, mixed>  $data
     */
    private function actorId(array $data = []): ?int
    {
        $authId = Auth::id();
        if ($authId !== null) {
            return (int) $authId;
        }

        return isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
    }

    /**
     * Confirm a sales invoice atomically.
     *
     * Idempotent when $data['client_uuid'] is set: a replay of the same key returns the
     * original invoice (wasRecentlyCreated === false) with no new side effects.
     *
     * $requireOpenShift (DEC-SHIFT): the sale actions that apply the POS-10 shift rule pass true,
     * and the open shift is re-checked under lock inside the transaction (409 when it was closed
     * meanwhile, SaleShiftGuard::assertShiftStillOpen). Internal callers (seeders) leave it off.
     */
    public function confirmInvoice(array $data, bool $requireOpenShift = false): Invoice
    {
        $clientUuid = ! empty($data['client_uuid']) ? (string) $data['client_uuid'] : null;
        $storeId = $this->resolveInvoiceStoreId($data);

        // P0-4: fast idempotency replay before any preflight queries or transactions.
        if ($clientUuid !== null) {
            $existing = Invoice::where('client_uuid', $clientUuid)->first();
            if ($existing instanceof Invoice) {
                $this->assertReplayMatches($existing, (int) ($data['customer_id'] ?? 0), $storeId);

                return $existing;
            }
        }

        // BE-08: the server resolves and validates every line price (authenticated callers only). A manager
        // PIN is verified here, before the transaction, so rate-limiter hits are never rolled back.
        $actor = Auth::user();
        $priceGuard = new SalePriceGuard(
            $actor instanceof User ? $actor : null,
            isset($data['override']) && is_array($data['override']) ? $data['override'] : null,
        );
        $discountGuard = new SaleDiscountGuard($actor instanceof User ? $actor : null, $priceGuard);
        $priceCustomer = Customer::query()->find($data['customer_id'] ?? 0);
        if ($priceCustomer instanceof Customer) {
            $this->assertWalkInPaidInFull($priceCustomer, (string) ($data['payment_type'] ?? 'cash'));
            // POS-07: percent line discounts become amounts first, so the floor and the cap see real money.
            $data['items'] = $discountGuard->resolveLineDiscounts($priceCustomer, $storeId, (array) ($data['items'] ?? []));
            $priceGuard->preflight($priceCustomer, $storeId, $data['items']);
            $discountGuard->preflight(
                $priceCustomer,
                $storeId,
                $data['items'],
                (string) ($data['discount_type'] ?? 'fixed'),
                (string) ($data['discount_value'] ?? '0.000'),
            );
        }

        // SHOP-03: a manager PIN for a credit sale above the customer's limit is verified here too (after the
        // price / discount approvals, whose verified PIN it reuses), before the transaction.
        $creditGuard = new CustomerCreditLimitGuard($actor instanceof User ? $actor : null, $priceGuard);
        if ($priceCustomer instanceof Customer) {
            $creditGuard->preflight($priceCustomer, $storeId, $data);
        }

        $invoicePrefix = empty($data['invoice_number']) ? $this->invoiceNumberPrefix($storeId) : null;

        // BUG-04: the day's counter rows exist before the transaction takes its first read.
        $this->prepareNumberScopes($data, $storeId, $invoicePrefix);

        try {
            return $this->persistConfirmedInvoice($data, $clientUuid, $storeId, $invoicePrefix, $priceGuard, $discountGuard, $creditGuard, $requireOpenShift);
        } catch (UniqueConstraintViolationException $e) {
            // Backstop for a concurrent insert of the same client_uuid that slipped past the
            // replay check. Any other unique violation (e.g. invoice_number) is rethrown.
            if ($clientUuid === null) {
                throw $e;
            }

            $existing = Invoice::where('client_uuid', $clientUuid)->first();
            if (! $existing) {
                throw $e;
            }

            $this->assertReplayMatches($existing, (int) $data['customer_id'], $storeId);

            return $existing;
        }
    }

    /**
     * POS-02: the generic walk-in (cash) customer is anonymous, so a credit or partial sale on it
     * would be an uncollectable receivable. It must be paid in full.
     *
     * @throws ValidationException
     */
    private function assertWalkInPaidInFull(Customer $customer, string $paymentType): void
    {
        if ($paymentType !== 'cash' && app(ResolveWalkInCustomerAction::class)->isWalkIn($customer)) {
            throw ValidationException::withMessages([
                'payment_type' => __('invoices.walk_in_must_pay_full'),
            ]);
        }
    }

    /**
     * BUG-04: create the counter rows this checkout will allocate from (invoice, payment, and
     * expense numbers) before its transaction. Consumes no number.
     */
    private function prepareNumberScopes(array $data, ?int $storeId = null, ?string $invoicePrefix = null): void
    {
        $numbers = app(DocumentNumberService::class);
        $resolvedStoreId = $storeId ?? $this->resolveInvoiceStoreId($data);

        if (empty($data['invoice_number'])) {
            $numbers->prepareFor($invoicePrefix ?? $this->invoiceNumberPrefix($resolvedStoreId), Invoice::class, 'invoice_number');
        }
        if (($data['payment_type'] ?? 'cash') !== 'credit') {
            $numbers->prepareDated('PAY-INV', Payment::class, 'payment_number');
        }
        if (! empty($data['additional_expenses']) || ! empty($data['shipping_cost'])) {
            $numbers->prepareDated('EXP', Expense::class, 'expense_number');
        }
    }

    /**
     * BUG-07: TransactionRetry restarts the whole closure on a lock conflict (1213/1205/1020).
     * It only captures plain input ($data) and the pre-transaction guards, whose approvals are
     * memoised (a retry re-reads the same approver, no new PIN/limiter hit); every row is read
     * again inside, and the invoice/payment numbers of a failed attempt roll back with it.
     */
    private function persistConfirmedInvoice(
        array $data,
        ?string $clientUuid,
        ?int $storeId,
        ?string $invoicePrefix,
        SalePriceGuard $priceGuard,
        SaleDiscountGuard $discountGuard,
        CustomerCreditLimitGuard $creditGuard,
        bool $requireOpenShift,
    ): Invoice {
        $storeName = $storeId !== null ? (string) (Store::query()->whereKey($storeId)->value('name') ?? "#{$storeId}") : null;
        $allowNegativeStock = app(TenantSettings::class)->allowNegativeStock();
        $currencyLabel = app(TenantSettings::class)->currencyLabel();
        $actorId = $this->actorId($data);

        return TransactionRetry::run(
            function () use ($data, $clientUuid, $storeId, $invoicePrefix, $storeName, $allowNegativeStock, $currencyLabel, $actorId, $priceGuard, $discountGuard, $creditGuard, $requireOpenShift) {
                $paymentType = (string) ($data['payment_type'] ?? 'cash');
                $affectsCustomerBalance = in_array($paymentType, ['credit', 'partial'], true);

                // P0-1: Only lock the customer row when the sale affects customer receivables (credit / partial).
                // Cash sales (including the shared walk-in customer) leave the customer balance unchanged and
                // avoid serializing concurrent cashiers on one customer row.
                $customer = $affectsCustomerBalance
                    ? Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail()
                    : Customer::where('id', $data['customer_id'])->firstOrFail();

                $lines = (array) ($data['items'] ?? []);
                $itemIds = array_column($lines, 'item_id');

                // BUG-07 / P0-3 lock order: (optional) customer -> items / store_stocks (sorted ascending by item_id) -> number sequences.
                $this->stockService->lockStockRows($itemIds, [$storeId]);

                if ($clientUuid !== null) {
                    // Plain read on purpose: a FOR UPDATE on a key that does not exist yet takes a gap lock
                    // on the unique index (MySQL 1213). Concurrent same-key inserts hit UniqueConstraintViolationException
                    // in confirmInvoice().
                    $existing = Invoice::where('client_uuid', $clientUuid)->first();
                    if ($existing) {
                        $this->assertReplayMatches($existing, (int) $customer->id, $storeId);

                        return $existing;
                    }
                }

                // POS-02: after the idempotency replay, so an offline retry of a saved invoice replays.
                $this->assertWalkInPaidInFull($customer, $paymentType);

                // PLAN-ENF: the monthly sales-invoice cap (max_invoices_per_month). Unlimited plans return before locking.
                $this->planEnforcer->assertWithinLimit(
                    LimitResource::InvoicesMonth,
                    fn (): int => $this->monthlyInvoiceCounter->count(),
                );

                // P0-3: Batch load already-locked items and store stocks in deterministic order (0 N+1 queries).
                $lockedItems = $this->stockService->loadLockedItems($itemIds);
                $lockedStoreStocks = $storeId !== null
                    ? $this->stockService->loadLockedStoreStocks($storeId, $lockedItems)
                    : [];

                foreach ($lockedItems as $lockedItemId => $lockedItem) {
                    $storeStock = $lockedStoreStocks[$lockedItemId] ?? null;
                    $lockedItem->setRelation(
                        'storeStocks',
                        new EloquentCollection($storeStock !== null ? [$storeStock] : []),
                    );
                }

                $itemStocks = [];
                foreach ($lockedItems as $lockedItemId => $lockedItem) {
                    $itemStocks[$lockedItemId] = (string) $lockedItem->current_stock;
                }
                $storeStockQuantities = [];
                foreach ($lockedStoreStocks as $lockedItemId => $storeStock) {
                    $storeStockQuantities[$lockedItemId] = (string) $storeStock->quantity;
                }

                // P0-4 / P0-2: Validate every line, evaluate pricing, check stock, compute discounts,
                // expenses and payments BEFORE allocating any document number (keeps sequence locks minimal).
                $discreteUnits = ['قطعة', 'حبة', 'علبة', 'باكت', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'جوال', 'piece', 'pcs', 'box', 'carton', 'pack', 'unit'];
                $subtotal = '0.000';
                $grossTotal = '0.000';
                $lineDiscountsTotal = '0.000';
                $totalCost = '0.000';
                $preparedItems = [];
                $preparedMovements = [];
                $dirtyItems = [];
                $dirtyStoreStocks = [];

                foreach ($lines as $lineIndex => $line) {
                    $itemId = (int) ($line['item_id'] ?? 0);
                    $item = $lockedItems[$itemId] ?? Item::where('id', $itemId)->lockForUpdate()->firstOrFail();

                    $qty = Decimal::normalize((string) $line['quantity']);

                    // Enforce integer-only quantity for discrete units
                    $itemUnit = trim(mb_strtolower((string) $item->unit));
                    if (in_array($itemUnit, $discreteUnits, true) && fmod((float) $qty, 1.0) != 0.0) {
                        throw BusinessRuleException::make('invoices.discrete_unit_integer_only', ['item' => $item->name, 'quantity' => $qty, 'unit' => $item->unit]);
                    }

                    $itemDiscount = Decimal::normalize((string) ($line['discount_amount'] ?? '0.000'));

                    $priceAudit = $priceGuard->evaluate(
                        $item,
                        $customer,
                        $storeId,
                        $qty,
                        isset($line['unit_price']) ? (string) $line['unit_price'] : null,
                        $itemDiscount,
                        (int) $lineIndex,
                    );
                    $unitPrice = $priceAudit['unit_price'];

                    // Check stock availability atomically against current locked balances
                    $stockBefore = $itemStocks[$itemId] ?? (string) $item->current_stock;
                    if (! $allowNegativeStock && bccomp($stockBefore, $qty, 3) < 0) {
                        throw InsufficientStockException::forItem($item->name, $stockBefore);
                    }
                    $stockAfter = bcsub($stockBefore, $qty, 3);
                    $itemStocks[$itemId] = $stockAfter;
                    $dirtyItems[$itemId] = $item;

                    if ($storeId !== null) {
                        $storeStock = $lockedStoreStocks[$itemId] ?? null;
                        if ($storeStock === null) {
                            throw InsufficientStockException::forStore($item->name, $storeName ?? "#{$storeId}", '0.000', (string) $item->unit);
                        }
                        $branchBefore = $storeStockQuantities[$itemId] ?? (string) $storeStock->quantity;
                        if (! $allowNegativeStock && bccomp($branchBefore, $qty, 3) < 0) {
                            throw InsufficientStockException::forStore(
                                $item->name,
                                $storeName ?? "#{$storeId}",
                                $branchBefore,
                                (string) $item->unit,
                            );
                        }
                        $storeStockQuantities[$itemId] = bcsub($branchBefore, $qty, 3);
                        $dirtyStoreStocks[$itemId] = $storeStock;
                    }

                    // Line Total = (Quantity * Unit Price) - Item Discount
                    // SETG-13: half-up at scale 3, identical to the POS (helpers/decimal.js dMul).
                    $grossLineTotal = Decimal::mul($qty, $unitPrice);
                    $netLineTotal = bcsub($grossLineTotal, $itemDiscount, 3);
                    if (bccomp($netLineTotal, '0.000', 3) < 0) {
                        $netLineTotal = '0.000';
                    }

                    $effectiveCost = bccomp($item->weighted_avg_cost, '0.000', 3) > 0
                        ? $item->weighted_avg_cost
                        : $item->cost_price;

                    $lineCost = Decimal::mul($qty, $effectiveCost);
                    $totalCost = bcadd($totalCost, $lineCost, 3);

                    $entrySequence = isset($line['entry_sequence']) && $line['entry_sequence'] !== ''
                        ? (int) $line['entry_sequence']
                        : ((int) $lineIndex + 1);

                    $preparedItems[] = [
                        'item_id' => $item->id,
                        'entry_sequence' => $entrySequence,
                        'quantity' => $qty,
                        'cost_price' => Decimal::normalize((string) $effectiveCost),
                        'unit_price' => Decimal::normalize((string) $unitPrice),
                        'list_unit_price' => Decimal::normalize((string) $priceAudit['list_unit_price']),
                        'price_source' => $priceAudit['price_source'],
                        'override_approved_by' => $priceAudit['override_approved_by'],
                        'discount_amount' => $itemDiscount,
                        'total_price' => $netLineTotal,
                    ];

                    $preparedMovements[] = [
                        'item_id' => $item->id,
                        'store_id' => $storeId,
                        'movement_type' => 'sales_out',
                        'quantity' => $qty,
                        'stock_before' => Decimal::normalize($stockBefore),
                        'stock_after' => Decimal::normalize($stockAfter),
                        'unit_cost' => Decimal::normalize((string) $item->cost_price),
                        'source_type' => Invoice::class,
                        'user_id' => $actorId,
                    ];

                    $subtotal = bcadd($subtotal, $netLineTotal, 3);
                    $grossTotal = bcadd($grossTotal, $grossLineTotal, 3);
                    $lineDiscountsTotal = bcadd($lineDiscountsTotal, $itemDiscount, 3);
                }

                // S3: Invoice-level Discount Calculation
                $discountType = $data['discount_type'] ?? 'fixed';
                $discountValue = $data['discount_value'] ?? '0.000';
                $invoiceDiscountAmount = SaleDiscountGuard::invoiceDiscountAmount($subtotal, (string) $discountType, (string) $discountValue);

                // POS-07: the cap on the exact totals (the approver, if any, was resolved before the transaction).
                $discountGuard->assertWithinCap($storeId, $grossTotal, bcadd($lineDiscountsTotal, $invoiceDiscountAmount, 3));

                // S3.5: Prepare Dynamic Additional Expenses
                $rawExpenses = $data['additional_expenses'] ?? [];
                $customerExpensesTotal = '0.000';
                $preparedExpenses = [];

                if (! empty($data['shipping_cost']) && bccomp((string) $data['shipping_cost'], '0.000', 3) > 0 && empty($rawExpenses)) {
                    $rawExpenses[] = [
                        'title' => 'مصاريف شحن وتوصيل',
                        'amount' => (string) $data['shipping_cost'],
                        'allocation_method' => 'by_quantity',
                        'paid_by' => 'customer_account',
                    ];
                }

                foreach ($rawExpenses as $exp) {
                    $expAmount = (string) ($exp['amount'] ?? '0.000');
                    if (bccomp($expAmount, '0.000', 3) <= 0) {
                        continue;
                    }
                    $paidBy = $exp['paid_by'] ?? 'customer_account';
                    if ($paidBy === 'customer_account' || $paidBy === 'supplier_account') {
                        $customerExpensesTotal = bcadd($customerExpensesTotal, $expAmount, 3);
                    }
                    $preparedExpenses[] = [
                        'title' => trim($exp['title'] ?? 'مصاريف إضافية'),
                        'amount' => $expAmount,
                        'allocation_method' => $exp['allocation_method'] ?? 'by_quantity',
                        'paid_by' => $paidBy,
                        'notes' => $exp['notes'] ?? null,
                    ];
                }

                $afterDiscount = bcsub($subtotal, $invoiceDiscountAmount, 3);
                $netTotal = bcadd($afterDiscount, $customerExpensesTotal, 3);

                // S4: Payments and Remaining Amounts (bounds enforced; a violation rolls back the whole transaction)
                $checkout = $this->resolveCheckoutPayments(
                    paymentType: $paymentType,
                    payments: $this->normalizeSplitPayments($data['payments'] ?? []),
                    netTotal: $netTotal,
                    paidAmountInput: isset($data['paid_amount']) ? (string) $data['paid_amount'] : null,
                    fallbackMethod: (string) ($data['payment_method'] ?? PaymentMethod::Cash->value),
                );
                $paidAmount = $checkout['paid'];
                $remainingAmount = bcsub($netTotal, $paidAmount, 3);

                if (! $affectsCustomerBalance && bccomp($remainingAmount, '0.000', 3) !== 0) {
                    throw new \LogicException('Non-credit invoice unexpectedly has a remaining balance.');
                }

                $paymentStatus = 'unpaid';
                if (bccomp($remainingAmount, '0.000', 3) === 0) {
                    $paymentStatus = 'paid';
                } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                    $paymentStatus = 'partially_paid';
                }

                // DEC-SHIFT: a sale subject to the shift rule (POS-10) is refused (409, everything above
                // rolls back) when its store's shift was closed after the pre-transaction check.
                if ($requireOpenShift) {
                    // SHIFT-01: in a per-cashier branch, the seller's own shift (the invoice's user_id).
                    app(SaleShiftGuard::class)->assertShiftStillOpen($storeId, $actorId);
                }

                // P0-2: Allocate document numbers as late as possible in the transaction, after all
                // validations, stock checks, pricing, discount caps, shift checks and payments succeeded.
                $invoiceDate = $data['invoice_date'] ?? $this->tenantClock->businessDate();
                $invoiceNumber = $data['invoice_number']
                    ?? ($invoicePrefix !== null
                        ? app(DocumentNumberService::class)->nextFor($invoicePrefix, 4, Invoice::class, 'invoice_number')
                        : $this->generateUniqueNumber($storeId));

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'client_uuid' => $clientUuid,
                    'customer_id' => $customer->id,
                    'user_id' => $actorId,
                    'store_id' => $storeId,
                    // SETG-2 ext: the business date on the tenant clock (cutoff-aware), never the server date.
                    'invoice_date' => $invoiceDate,
                    'payment_type' => $paymentType,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'status' => 'confirmed',
                    'payment_status' => $paymentStatus,
                    'subtotal' => $subtotal,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $invoiceDiscountAmount,
                    'discount_override_by' => $priceGuard->discountApproverId() ?? $priceGuard->invoiceDiscountFloorApproverId(),
                    'shipping_cost' => $customerExpensesTotal,
                    'net_total' => $netTotal,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'change_amount' => $checkout['change'],
                    'total_cost' => $totalCost,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Persist updated stock balances once per touched item / store_stock.
                foreach ($dirtyStoreStocks as $itemId => $storeStock) {
                    $storeStock->quantity = $storeStockQuantities[$itemId];
                    $storeStock->save();
                }
                foreach ($dirtyItems as $itemId => $item) {
                    $item->current_stock = $itemStocks[$itemId];
                    $item->save();
                }

                $now = Carbon::now();
                if ($preparedItems !== []) {
                    $invoiceItemRows = array_map(
                        static fn (array $row): array => [
                            ...$row,
                            'invoice_id' => $invoice->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $preparedItems,
                    );
                    InvoiceItem::insert($invoiceItemRows);
                }

                if ($preparedMovements !== []) {
                    $movementNotes = "صرف مبيعات بالفاتورة رقم {$invoiceNumber}";
                    $stockMovementRows = array_map(
                        static fn (array $row): array => [
                            ...$row,
                            'source_id' => $invoice->id,
                            'document_number' => $invoiceNumber,
                            'notes' => $movementNotes,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $preparedMovements,
                    );
                    StockMovement::insert($stockMovementRows);
                }

                foreach ($preparedExpenses as $exp) {
                    $expenseRecord = $invoice->additionalExpenses()->create($exp);
                    if ($exp['paid_by'] !== 'customer_account' && $exp['paid_by'] !== 'supplier_account') {
                        $paymentMethod = str_replace('treasury_', '', $exp['paid_by']) ?: 'cash';
                        $expenseNumber = app(DocumentNumberService::class)->nextDated('EXP', 4, Expense::class, 'expense_number');
                        Expense::create([
                            'expense_number' => $expenseNumber,
                            'category' => 'shipping',
                            'title' => "مصروف فاتورة [{$invoiceNumber}]: {$exp['title']}",
                            'amount' => $exp['amount'],
                            'payment_method' => $paymentMethod,
                            'expense_date' => $invoice->invoice_date,
                            'store_id' => $invoice->store_id,
                            'user_id' => $actorId,
                            'notes' => "مصروف خدمات/شحن مسدد من الخزينة لفاتورة مبيعات رقم {$invoiceNumber}",
                        ]);

                        $expenseRecord->update([
                            'notes' => ($exp['notes'] ? "{$exp['notes']} | " : '')."رقم المصروف: {$expenseNumber}",
                        ]);
                    }
                }

                // S5: One voucher per resolved line. Lines already exclude the change, so the
                // recorded payments sum exactly to paid_amount (never inflating balance/treasury).
                $voucherNote = $checkout['split']
                    ? __('invoices.payment_note_split', ['number' => $invoiceNumber])
                    : __('invoices.payment_note_on_issue', ['number' => $invoiceNumber]);

                foreach ($checkout['lines'] as $line) {
                    Payment::create([
                        'payment_number' => app(DocumentNumberService::class)->nextDated('PAY-INV', 6, Payment::class, 'payment_number'),
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'store_id' => $invoice->store_id,
                        'user_id' => $actorId,
                        'amount' => $line['amount'],
                        'payment_date' => $invoice->invoice_date,
                        'payment_method' => $line['method'],
                        'notes' => $voucherNote,
                    ]);
                }

                // S6 / P0-1: Only credit/partial sales change customer receivables and touch the customer balance.
                if ($affectsCustomerBalance) {
                    $balanceAfter = $this->customerBalanceService->updateBalance($customer->id);

                    // SHOP-03: the credit limit, on the customer row locked above and the exact new balance.
                    $creditGuard->assertWithinLimit($customer, bcsub($balanceAfter, $remainingAmount, 3), $balanceAfter, $invoice);
                }

                // S7 / P0-4: Defer audit and activity logging until after commit so locks are already released.
                $auditPayload = $invoice->toArray();
                $customerName = (string) $customer->name;
                DB::afterCommit(function () use ($invoice, $customer, $customerName, $auditPayload, $actorId, $currencyLabel): void {
                    $invoice->setRelation('customer', $customer);
                    $this->auditLogService->log(
                        action: 'invoice_confirmed',
                        auditable: $invoice,
                        oldValues: null,
                        newValues: $auditPayload,
                        actorId: $actorId,
                    );

                    $this->activityLogService->logSales(
                        action: 'created',
                        invoice: $invoice,
                        description: "تم إنشاء واعتماد فاتورة مبيعات جديدة رقم [{$invoice->invoice_number}] للعميل ({$customerName}) بقيمة ".number_format((float) $invoice->net_total, 2).' '.$currencyLabel,
                    );
                });

                return $invoice;
            },
            lockWaitTimeoutSeconds: TransactionRetry::checkoutLockWaitTimeout(),
        );
    }

    private function resolveInvoiceStoreId(array $data): ?int
    {
        $storeId = $data['store_id'] ?? Auth::user()?->getCurrentStore()?->id ?? Store::getMainStore()?->id;

        return $storeId !== null ? (int) $storeId : null;
    }

    /**
     * A replayed client_uuid must belong to the same customer and store; otherwise the key
     * was reused for a different sale and must not leak the original invoice.
     */
    private function assertReplayMatches(Invoice $existing, int $customerId, ?int $storeId): void
    {
        $existingStoreId = $existing->store_id !== null ? (int) $existing->store_id : null;

        if ((int) $existing->customer_id !== $customerId || $existingStoreId !== $storeId) {
            throw ValidationException::withMessages([
                'client_uuid' => [__('invoices.idempotency_key_conflict')],
            ]);
        }
    }

    /**
     * Resolve what is actually paid on checkout (split-payment contract, CTO option A).
     *
     * - credit:  no payments allowed; paid 0.
     * - partial: 0 < paid < net (payments[] total, else paid_amount); never any change.
     * - cash (any other type) with payments[]: total may exceed net only through cash.
     *   Non-cash above net or total below net is rejected. The change is trimmed off the
     *   cash lines from the LAST one backwards; lines reaching 0.000 are dropped, so the
     *   resolved lines always sum exactly to net.
     * - cash without payments[]: paid = net in a single line with $fallbackMethod.
     *
     * Pure calculation: it runs inside the caller's transaction and writes nothing.
     *
     * @param  list<array{method: string, amount: string}>  $payments
     * @return array{paid: string, change: string, split: bool, lines: list<array{method: string, amount: string}>}
     *
     * @throws ValidationException
     */
    private function resolveCheckoutPayments(
        string $paymentType,
        array $payments,
        string $netTotal,
        ?string $paidAmountInput,
        string $fallbackMethod,
    ): array {
        $split = $payments !== [];
        $lines = [];
        $total = '0.000';
        $cashTotal = '0.000';
        $nonCashTotal = '0.000';

        foreach ($payments as $p) {
            $amount = bcadd($p['amount'], '0', 3);
            $lines[] = ['method' => $p['method'], 'amount' => $amount];
            $total = bcadd($total, $amount, 3);
            if ($p['method'] === PaymentMethod::Cash->value) {
                $cashTotal = bcadd($cashTotal, $amount, 3);
            } else {
                $nonCashTotal = bcadd($nonCashTotal, $amount, 3);
            }
        }

        if ($paymentType === 'credit') {
            if ($split) {
                throw ValidationException::withMessages([
                    'payments' => __('invoices.credit_invoice_cannot_have_payments'),
                ]);
            }

            return ['paid' => '0.000', 'change' => '0.000', 'split' => false, 'lines' => []];
        }

        if ($paymentType === 'partial') {
            $paid = $split ? $total : bcadd($paidAmountInput ?? '0', '0', 3);
            if (! (bccomp($paid, '0.000', 3) > 0 && bccomp($paid, $netTotal, 3) < 0)) {
                throw ValidationException::withMessages([
                    'paid_amount' => __('invoices.partial_paid_out_of_range', ['net' => $netTotal]),
                ]);
            }

            if (! $split) {
                $lines = [['method' => $fallbackMethod, 'amount' => $paid]];
            }

            return ['paid' => $paid, 'change' => '0.000', 'split' => $split, 'lines' => $lines];
        }

        // cash (and any fully-paid type such as bank_transfer)
        if (! $split) {
            $lines = bccomp($netTotal, '0.000', 3) > 0
                ? [['method' => $fallbackMethod, 'amount' => $netTotal]]
                : [];

            return ['paid' => $netTotal, 'change' => '0.000', 'split' => false, 'lines' => $lines];
        }

        if (bccomp($nonCashTotal, $netTotal, 3) > 0) {
            throw ValidationException::withMessages([
                'payments' => __('invoices.split_payment_non_cash_exceeds_due', ['non_cash' => $nonCashTotal, 'net' => $netTotal]),
            ]);
        }

        if (bccomp($total, $netTotal, 3) < 0) {
            throw ValidationException::withMessages([
                'payments' => __('invoices.split_payment_below_net', ['paid' => $total, 'net' => $netTotal]),
            ]);
        }

        // nonCash <= net <= total  =>  change = total - net <= cashTotal, so cash always covers it.
        $change = bcsub($total, $netTotal, 3);
        $toTrim = $change;
        for ($i = count($lines) - 1; $i >= 0 && bccomp($toTrim, '0.000', 3) > 0; $i--) {
            if ($lines[$i]['method'] !== PaymentMethod::Cash->value) {
                continue;
            }
            $take = bccomp($lines[$i]['amount'], $toTrim, 3) <= 0 ? $lines[$i]['amount'] : $toTrim;
            $lines[$i]['amount'] = bcsub($lines[$i]['amount'], $take, 3);
            $toTrim = bcsub($toTrim, $take, 3);
        }

        $lines = array_values(array_filter(
            $lines,
            static fn (array $line): bool => bccomp($line['amount'], '0.000', 3) > 0
        ));

        $resolvedTotal = '0.000';
        foreach ($lines as $line) {
            $resolvedTotal = bcadd($resolvedTotal, $line['amount'], 3);
        }
        if (bccomp($toTrim, '0.000', 3) !== 0 || bccomp($resolvedTotal, $netTotal, 3) !== 0) {
            // Unreachable given the checks above; guards the ledger against a future regression.
            throw new \LogicException('Resolved checkout payments do not sum to the invoice net.');
        }

        return ['paid' => $netTotal, 'change' => $change, 'split' => true, 'lines' => $lines];
    }

    /**
     * Normalize split payment lines to ['method' => string, 'amount' => string] with
     * a positive amount; non-array input (e.g. null) yields an empty list.
     *
     * @return list<array{method: string, amount: string}>
     */
    private function normalizeSplitPayments(mixed $payments): array
    {
        if (! is_array($payments)) {
            return [];
        }

        $normalized = [];
        foreach ($payments as $p) {
            if (! is_array($p)) {
                continue;
            }
            $amount = (string) ($p['amount'] ?? '0.000');
            if (bccomp($amount, '0.000', 3) <= 0) {
                continue;
            }
            $normalized[] = [
                'method' => (string) ($p['method'] ?? 'cash'),
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    /**
     * Cancel an invoice and reverse stock securely
     */
    public function cancelInvoice(Invoice $invoice, string $reason, ?int $approvedBy = null): Invoice
    {
        // BUG-04: the day's refund-voucher counter row exists before the transaction starts.
        app(DocumentNumberService::class)->prepareDated('PAY-REF', Payment::class, 'payment_number');
        $invoiceId = (int) $invoice->id;

        // BUG-07: restarted as a whole on a lock conflict; only the id is captured.
        return TransactionRetry::run(function () use ($invoiceId, $reason, $approvedBy) {
            $lockedInvoice = Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === 'cancelled') {
                throw BusinessRuleException::conflict('invoices.already_cancelled');
            }

            // BE-03: the PIN requirement was decided before the lock. A cash payment may have landed since
            // (a concurrent request), so re-check on the locked row: no approver now means a PIN is required.
            if ($approvedBy === null && app(InvoiceCancelGuard::class)->hasActiveCashPayments($lockedInvoice)) {
                throw ValidationException::withMessages([
                    'override' => __('invoices.cancel_cash_requires_admin_pin'),
                ]);
            }

            ReturnSourceService::assertNoLiveReturns($lockedInvoice);
            // BE-03 lock order: document -> party -> items -> number sequences.
            if ($lockedInvoice->customer_id) {
                Customer::query()->whereKey($lockedInvoice->customer_id)->lockForUpdate()->first();
            }
            // BUG-07: the lines' items / store_stocks in the global sorted order, not line order.
            $this->stockService->lockStockRows($lockedInvoice->items->pluck('item_id'), [$lockedInvoice->store_id]);

            $oldState = $lockedInvoice->toArray();

            // Reverse stock for each line
            foreach ($lockedInvoice->items as $itemLine) {
                $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->firstOrFail();

                $this->stockService->addStock(
                    item: $item,
                    quantity: $itemLine->quantity,
                    unitCost: $itemLine->cost_price,
                    source: $lockedInvoice,
                    documentNumber: $lockedInvoice->invoice_number,
                    movementType: 'cancellation_in',
                    notes: "إلغاء فاتورة مبيعات رقم {$lockedInvoice->invoice_number} - سبب: {$reason}",
                    storeId: $lockedInvoice->store_id
                );
            }

            $noteText = ($lockedInvoice->notes ? $lockedInvoice->notes."\n" : '')."تم الإلغاء: {$reason}";

            $lockedInvoice->update([
                'status' => 'cancelled',
                'remaining_amount' => '0.000',
                'notes' => $noteText,
            ]);

            // BE-03: give back what was paid (negative PAY-REF vouchers) before the balance is recomputed
            app(ReverseInvoicePaymentsAction::class)->execute($lockedInvoice);

            // Recalculate customer balance
            $this->customerBalanceService->updateBalance($lockedInvoice->customer_id);

            $newValues = $lockedInvoice->toArray();
            if ($approvedBy !== null) {
                $newValues['override_approved_by'] = $approvedBy;
            }

            // Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_cancelled',
                auditable: $lockedInvoice,
                oldValues: $oldState,
                newValues: $newValues
            );

            $salesProperties = ['reason' => $reason];
            if ($approvedBy !== null) {
                $salesProperties['override_approved_by'] = $approvedBy;
            }

            $this->activityLogService->logSales(
                action: 'cancelled',
                invoice: $lockedInvoice,
                description: "تم إلغاء فاتورة المبيعات رقم [{$lockedInvoice->invoice_number}] وإرجاع بضاعتها للمخزن. سبب الإلغاء: {$reason}",
                properties: $salesProperties
            );

            return $lockedInvoice;
        });
    }

    /**
     * Update an existing confirmed invoice atomically with inventory and balance adjustments
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        // BE-08 / POS-07: the same price floor, tier and discount-cap guards as confirmInvoice(). A manager PIN is
        // verified here, before the transaction, so failed-PIN limiter hits are never rolled back.
        $actor = Auth::user();
        $priceGuard = new SalePriceGuard(
            $actor instanceof User ? $actor : null,
            isset($data['override']) && is_array($data['override']) ? $data['override'] : null,
        );
        $discountGuard = new SaleDiscountGuard($actor instanceof User ? $actor : null, $priceGuard);
        $priceCustomer = Customer::query()->find($data['customer_id'] ?? 0);
        $currentInvoice = Invoice::query()->find($invoice->id);
        if ($priceCustomer instanceof Customer && $currentInvoice instanceof Invoice && $currentInvoice->status !== 'cancelled') {
            $priceStoreId = $currentInvoice->store_id !== null ? (int) $currentInvoice->store_id : null;
            $data['items'] = $discountGuard->resolveLineDiscounts($priceCustomer, $priceStoreId, (array) ($data['items'] ?? []));
            $priceGuard->preflight($priceCustomer, $priceStoreId, $data['items']);
            $discountGuard->preflight(
                $priceCustomer,
                $priceStoreId,
                $data['items'],
                (string) ($data['discount_type'] ?? 'fixed'),
                (string) ($data['discount_value'] ?? '0.000'),
            );
        }

        // SHOP-03: the credit-limit manager PIN, before the transaction. The old invoice's debt is already
        // in the balance when the customer does not change, so it is not counted twice.
        $creditGuard = new CustomerCreditLimitGuard($actor instanceof User ? $actor : null, $priceGuard);
        if ($priceCustomer instanceof Customer && $currentInvoice instanceof Invoice && $currentInvoice->status !== 'cancelled') {
            $oldDebt = (int) $currentInvoice->customer_id === (int) $priceCustomer->id ? Decimal::normalize((string) $currentInvoice->remaining_amount) : '0.000';
            $creditGuard->preflight(
                $priceCustomer,
                $currentInvoice->store_id !== null ? (int) $currentInvoice->store_id : null,
                $data,
                $oldDebt,
            );
        }

        // BUG-04: the day's payment counter row exists before the transaction takes its first read.
        if (($data['payment_type'] ?? 'cash') !== 'credit') {
            app(DocumentNumberService::class)->prepareDated('PAY-INV', Payment::class, 'payment_number');
        }
        $invoiceId = (int) $invoice->id;

        // BUG-07: restarted as a whole on a lock conflict (1213/1205/1020). Only the id of the
        // passed invoice is used; the row is locked and read again by every attempt.
        return TransactionRetry::run(function () use ($invoiceId, $data, $priceGuard, $discountGuard, $creditGuard) {
            $lockedInvoice = Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();
            if ($lockedInvoice->status === 'cancelled') {
                throw BusinessRuleException::conflict('invoices.cannot_edit_cancelled');
            }
            ReturnSourceService::assertNoLiveReturns($lockedInvoice);
            $oldCustomerId = $lockedInvoice->customer_id;
            $newCustomerId = $data['customer_id'];
            // BE-09 lock order (same as persistConfirmedInvoice): parties first, in id order, before
            // item locks and before any document-number allocation below.
            Customer::query()
                ->whereIn('id', array_values(array_unique(array_filter([(int) $oldCustomerId, (int) $newCustomerId]))))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $customer = Customer::query()->findOrFail($newCustomerId);
            $this->assertWalkInPaidInFull($customer, (string) ($data['payment_type'] ?? 'cash'));
            // SHOP-03: the new customer's exact balance before this edit (row locked above), only when a limit applies.
            $creditBalanceBefore = $customer->credit_limit !== null
                ? $this->customerBalanceService->updateBalance((int) $customer->id)
                : null;
            $storeId = $lockedInvoice->store_id !== null ? (int) $lockedInvoice->store_id : null;

            // BUG-07: the old and the new lines' stock rows, in the global sorted order.
            $this->stockService->lockStockRows(
                [...$lockedInvoice->items->pluck('item_id')->all(), ...array_column((array) ($data['items'] ?? []), 'item_id')],
                [$storeId],
            );

            // 1. If invoice was confirmed, reverse previous stock back to warehouse
            if ($lockedInvoice->status === 'confirmed') {
                foreach ($lockedInvoice->items as $oldLine) {
                    $item = Item::where('id', $oldLine->item_id)->lockForUpdate()->first();
                    if ($item) {
                        $this->stockService->addStock(
                            item: $item,
                            quantity: $oldLine->quantity,
                            unitCost: $oldLine->cost_price,
                            source: $lockedInvoice,
                            documentNumber: $lockedInvoice->invoice_number,
                            movementType: 'cancellation_in',
                            notes: "إرجاع مخزون لتعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                            storeId: $lockedInvoice->store_id
                        );
                    }
                }
            }

            // Delete old items and previous stock movements for this invoice
            $lockedInvoice->items()->delete();
            StockMovement::where('source_type', Invoice::class)
                ->where('source_id', $lockedInvoice->id)
                ->delete();

            // 2. Add new items, deduct new stock, calculate new subtotal and total cost
            $subtotal = '0.000';
            $grossTotal = '0.000';
            $lineDiscountsTotal = '0.000';
            $totalCost = '0.000';

            foreach ($data['items'] as $lineIndex => $line) {
                $item = Item::where('id', $line['item_id'])->lockForUpdate()->firstOrFail();
                $qty = (string) $line['quantity'];
                $itemDiscount = Decimal::normalize((string) ($line['discount_amount'] ?? '0.000'));
                $priceAudit = $priceGuard->evaluate(
                    $item,
                    $customer,
                    $storeId,
                    $qty,
                    isset($line['unit_price']) ? (string) $line['unit_price'] : null,
                    $itemDiscount,
                    (int) $lineIndex,
                );
                $unitPrice = $priceAudit['unit_price'];

                $grossLineTotal = Decimal::mul($qty, $unitPrice);
                $netLineTotal = bcsub($grossLineTotal, $itemDiscount, 3);
                if (bccomp($netLineTotal, '0.000', 3) < 0) {
                    $netLineTotal = '0.000';
                }

                $effectiveCost = bccomp($item->weighted_avg_cost, '0.000', 3) > 0
                    ? $item->weighted_avg_cost
                    : $item->cost_price;

                $lineCost = Decimal::mul($qty, $effectiveCost);
                $totalCost = bcadd($totalCost, $lineCost, 3);

                $entrySequence = isset($line['entry_sequence']) && $line['entry_sequence'] !== ''
                    ? (int) $line['entry_sequence']
                    : ((int) $lineIndex + 1);

                $lockedInvoice->items()->create([
                    'item_id' => $item->id,
                    'entry_sequence' => $entrySequence,
                    'quantity' => $qty,
                    'cost_price' => $effectiveCost,
                    'unit_price' => $unitPrice,
                    'list_unit_price' => $priceAudit['list_unit_price'],
                    'price_source' => $priceAudit['price_source'],
                    'override_approved_by' => $priceAudit['override_approved_by'],
                    'discount_amount' => $itemDiscount,
                    'total_price' => $netLineTotal,
                ]);

                $this->stockService->deductStock(
                    item: $item,
                    quantity: $qty,
                    source: $lockedInvoice,
                    documentNumber: $lockedInvoice->invoice_number,
                    movementType: 'sales_out',
                    notes: "صرف مبيعات بتعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                    storeId: $lockedInvoice->store_id,
                    actorId: $this->actorId($data),
                );

                $subtotal = bcadd($subtotal, $netLineTotal, 3);
                $grossTotal = bcadd($grossTotal, $grossLineTotal, 3);
                $lineDiscountsTotal = bcadd($lineDiscountsTotal, $itemDiscount, 3);
            }

            // 3. Invoice-level discount calculation
            $discountType = $data['discount_type'] ?? 'fixed';
            $discountValue = (string) ($data['discount_value'] ?? '0.000');
            // Same computation as confirmInvoice (SETG-13 half-up percent, capped at the subtotal).
            $invoiceDiscountAmount = SaleDiscountGuard::invoiceDiscountAmount($subtotal, (string) $discountType, $discountValue);

            // POS-07: the cap on the exact totals (the approver, if any, was resolved before the transaction).
            $discountGuard->assertWithinCap($storeId, $grossTotal, bcadd($lineDiscountsTotal, $invoiceDiscountAmount, 3));

            // 3.5. Dynamic additional expenses in edit
            $rawExpenses = $data['additional_expenses'] ?? [];
            $additionalExpensesTotal = '0.000';
            $customerExpensesTotal = '0.000';

            // Delete old additional expenses and their payment vouchers
            foreach ($lockedInvoice->additionalExpenses as $oldExp) {
                if ($oldExp->payment_id) {
                    Payment::where('id', $oldExp->payment_id)->delete();
                }
                $oldExp->delete();
            }

            if (! empty($data['shipping_cost']) && bccomp((string) $data['shipping_cost'], '0.000', 3) > 0 && empty($rawExpenses)) {
                $rawExpenses[] = [
                    'title' => 'مصاريف شحن وتوصيل',
                    'amount' => (string) $data['shipping_cost'],
                    'allocation_method' => 'by_quantity',
                    'paid_by' => 'customer_account',
                ];
            }

            foreach ($rawExpenses as $exp) {
                $expAmount = (string) ($exp['amount'] ?? '0.000');
                if (bccomp($expAmount, '0.000', 3) <= 0) {
                    continue;
                }
                $title = trim($exp['title'] ?? 'مصاريف إضافية');
                $method = $exp['allocation_method'] ?? 'by_quantity';
                $paidBy = $exp['paid_by'] ?? 'customer_account';
                $expNotes = $exp['notes'] ?? null;

                $additionalExpensesTotal = bcadd($additionalExpensesTotal, $expAmount, 3);

                $expenseRecord = $lockedInvoice->additionalExpenses()->create([
                    'title' => $title,
                    'amount' => $expAmount,
                    'allocation_method' => $method,
                    'paid_by' => $paidBy,
                    'notes' => $expNotes,
                ]);

                if ($paidBy === 'customer_account' || $paidBy === 'supplier_account') {
                    $customerExpensesTotal = bcadd($customerExpensesTotal, $expAmount, 3);
                } else {
                    $paymentMethod = str_replace('treasury_', '', $paidBy) ?: 'cash';
                    $expenseNumber = app(DocumentNumberService::class)->nextDated('EXP', 4, Expense::class, 'expense_number');
                    $expense = Expense::create([
                        'expense_number' => $expenseNumber,
                        'category' => 'shipping',
                        'title' => "مصروف فاتورة [{$lockedInvoice->invoice_number}]: {$title}",
                        'amount' => $expAmount,
                        'payment_method' => $paymentMethod,
                        'expense_date' => $data['invoice_date'] ?? $lockedInvoice->invoice_date,
                        'store_id' => $lockedInvoice->store_id,
                        'user_id' => $this->actorId($data),
                        'notes' => "مصروف خدمات/شحن مسدد من الخزينة لفاتورة مبيعات رقم {$lockedInvoice->invoice_number}",
                    ]);

                    $expenseRecord->update(['notes' => ($expNotes ? "{$expNotes} | " : '')."رقم المصروف: {$expenseNumber}"]);
                }
            }

            $afterDiscount = bcsub($subtotal, $invoiceDiscountAmount, 3);
            $netTotal = bcadd($afterDiscount, $customerExpensesTotal, 3);

            // 4. Payment calculations
            $paymentType = $data['payment_type'] ?? 'cash';
            $paidAmount = '0.000';

            if ($paymentType === 'cash') {
                $paidAmount = $netTotal;
            } elseif ($paymentType === 'partial') {
                $paidAmount = (string) ($data['paid_amount'] ?? '0.000');
            } else {
                $paidAmount = '0.000';
            }

            $remainingAmount = bcsub($netTotal, $paidAmount, 3);
            if (bccomp($remainingAmount, '0.000', 3) < 0) {
                $remainingAmount = '0.000';
            }

            $paymentStatus = 'unpaid';
            if (bccomp($remainingAmount, '0.000', 3) === 0) {
                $paymentStatus = 'paid';
            } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                $paymentStatus = 'partially_paid';
            }

            // 5. Update invoice fields
            $lockedInvoice->update([
                'customer_id' => $newCustomerId,
                'invoice_date' => $data['invoice_date'] ?? $lockedInvoice->invoice_date,
                'payment_type' => $paymentType,
                'payment_method' => $data['payment_method'] ?? $lockedInvoice->payment_method ?? 'cash',
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $invoiceDiscountAmount,
                'shipping_cost' => $customerExpensesTotal,
                'net_total' => $netTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'total_cost' => $totalCost,
                'discount_override_by' => $priceGuard->discountApproverId() ?? $priceGuard->invoiceDiscountFloorApproverId(),
                'notes' => $data['notes'] ?? $lockedInvoice->notes,
            ]);

            // 6. Delete previous payments and re-create payment voucher(s) if paid
            Payment::where('invoice_id', $lockedInvoice->id)->excludingRefunds()->delete();

            if (! empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $p) {
                    $pAmount = (string) ($p['amount'] ?? '0.000');
                    if (bccomp($pAmount, '0.000', 3) > 0) {
                        Payment::create([
                            'payment_number' => app(DocumentNumberService::class)->nextDated('PAY-INV', 6, Payment::class, 'payment_number'),
                            'customer_id' => $newCustomerId,
                            'invoice_id' => $lockedInvoice->id,
                            'store_id' => $lockedInvoice->store_id,
                            'user_id' => $this->actorId($data),
                            'amount' => $pAmount,
                            'payment_date' => $lockedInvoice->invoice_date,
                            'payment_method' => $p['method'] ?? 'cash',
                            'notes' => "سداد مجزأ عند تعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                        ]);
                    }
                }
            } elseif (bccomp($paidAmount, '0.000', 3) > 0) {
                Payment::create([
                    'payment_number' => app(DocumentNumberService::class)->nextDated('PAY-INV', 6, Payment::class, 'payment_number'),
                    'customer_id' => $newCustomerId,
                    'invoice_id' => $lockedInvoice->id,
                    'store_id' => $lockedInvoice->store_id,
                    'user_id' => $this->actorId($data),
                    'amount' => $paidAmount,
                    'payment_date' => $lockedInvoice->invoice_date,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'notes' => "سداد عند تعديل الفاتورة رقم {$lockedInvoice->invoice_number}",
                ]);
            }

            // 7. Recalculate customer balances (both old and new)
            if ($oldCustomerId) {
                $this->customerBalanceService->updateBalance($oldCustomerId);
            }
            if ($newCustomerId && $newCustomerId !== $oldCustomerId) {
                $this->customerBalanceService->updateBalance($newCustomerId);
            }

            // SHOP-03: refused when the edit raises the customer's balance above their credit limit.
            if ($creditBalanceBefore !== null) {
                $customer->refresh();
                $creditGuard->assertWithinLimit($customer, $creditBalanceBefore, Decimal::normalize((string) $customer->current_balance), $lockedInvoice);
            }

            // 8. Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_updated',
                auditable: $lockedInvoice,
                oldValues: null,
                newValues: $lockedInvoice->toArray()
            );

            $this->activityLogService->logSales(
                action: 'updated',
                invoice: $lockedInvoice,
                description: "تم تعديل بيانات وأصناف فاتورة المبيعات رقم [{$lockedInvoice->invoice_number}] وإعادة احتساب الرصيد",
                properties: ['net_total' => (string) $lockedInvoice->net_total, 'paid_amount' => (string) $lockedInvoice->paid_amount]
            );

            return $lockedInvoice;
        });
    }

    /**
     * Permanently delete an invoice with complete stock and financial reversal
     */
    public function deleteInvoice(Invoice $invoice): bool
    {
        if (auth()->check() && ! auth()->user()->hasRole('admin')) {
            throw BusinessRuleException::forbidden('invoices.delete_admin_only');
        }

        $invoiceId = (int) $invoice->id;

        // BUG-07: restarted as a whole on a lock conflict; only the id is captured.
        return TransactionRetry::run(function () use ($invoiceId) {
            $lockedInvoice = Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail();
            ReturnSourceService::assertNoLiveReturns($lockedInvoice);
            $customerId = $lockedInvoice->customer_id;
            $invoiceNumber = $lockedInvoice->invoice_number;

            // BUG-07 lock order (as the sale and cancel): document -> party -> items / store_stocks
            // sorted. The customer used to be locked last (in the balance recompute), after the items.
            if ($customerId) {
                Customer::query()->whereKey($customerId)->lockForUpdate()->first();
            }
            $this->stockService->lockStockRows($lockedInvoice->items->pluck('item_id'), [$lockedInvoice->store_id]);

            // 1. If invoice was confirmed, reverse the inventory back to warehouse
            if ($lockedInvoice->status === 'confirmed') {
                foreach ($lockedInvoice->items as $itemLine) {
                    $item = Item::where('id', $itemLine->item_id)->lockForUpdate()->first();
                    if ($item) {
                        $this->stockService->addStock(
                            item: $item,
                            quantity: $itemLine->quantity,
                            unitCost: $itemLine->cost_price,
                            source: $lockedInvoice,
                            documentNumber: $invoiceNumber,
                            movementType: 'cancellation_in',
                            notes: "إرجاع مخزون بسبب حذف الفاتورة رقم {$invoiceNumber}",
                            storeId: $lockedInvoice->store_id
                        );
                    }
                }
            }

            // 2. Delete any payment vouchers linked directly to this invoice
            Payment::where('invoice_id', $lockedInvoice->id)->delete();

            // 3. Delete Stock movements linked to this invoice
            StockMovement::where('source_type', Invoice::class)
                ->where('source_id', $lockedInvoice->id)
                ->delete();

            // 4. Delete invoice items
            $lockedInvoice->items()->delete();

            // 5. Delete the invoice itself
            $lockedInvoice->delete();

            // 6. Recalculate customer balance
            if ($customerId) {
                $this->customerBalanceService->updateBalance($customerId);
            }

            // 7. Audit & Activity log
            $this->auditLogService->log(
                action: 'invoice_deleted',
                auditable: $lockedInvoice,
                oldValues: ['invoice_number' => $invoiceNumber],
                newValues: null
            );

            $this->activityLogService->log(
                module: 'sales',
                action: 'deleted',
                description: "تم حذف فاتورة المبيعات رقم [{$invoiceNumber}] وعكس أثرها المخزني والمالي",
                properties: ['invoice_number' => $invoiceNumber],
                storeId: $lockedInvoice->store_id
            );

            return true;
        });
    }

    /**
     * Allocates the next number from the locked sequence: it CONSUMES a number (no previews).
     * Call it inside the transaction that inserts the document, after the party/document locks.
     */
    public function generateUniqueNumber(?int $storeId = null): string
    {
        return app(DocumentNumberService::class)->nextFor($this->invoiceNumberPrefix($storeId), 4, Invoice::class, 'invoice_number');
    }

    /**
     * "INV-{branch code}-{business date}": the visible prefix and the counter scope of a
     * branch's invoice numbers.
     */
    private function invoiceNumberPrefix(?int $storeId): string
    {
        $storeCode = null;
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && ! empty($store->code)) {
                $storeCode = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($store->code));
            } elseif ($store) {
                $storeCode = 'B'.str_pad((string) $store->id, 2, '0', STR_PAD_LEFT);
            }
        }

        if (! $storeCode) {
            $mainStore = Store::getMainStore();
            $storeCode = ($mainStore && ! empty($mainStore->code))
                ? preg_replace('/[^A-Za-z0-9]/', '', strtoupper($mainStore->code))
                : 'MAIN';
        }

        // SETG-2 ext: the date in the number is the business date (tenant clock + cutoff).
        return "INV-{$storeCode}-".str_replace('-', '', $this->tenantClock->businessDate());
    }
}
