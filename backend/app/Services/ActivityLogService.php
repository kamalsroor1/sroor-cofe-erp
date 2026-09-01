<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\CashShift;
use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log a general activity event.
     */
    public function log(
        string $module,
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $properties = null,
        ?int $userId = null,
        ?int $storeId = null
    ): ActivityLog {
        $resolvedUserId = $userId ?: Auth::id();
        if ($resolvedUserId && !User::where('id', $resolvedUserId)->exists()) {
            $resolvedUserId = null;
        }
        
        $resolvedStoreId = $storeId 
            ?: session('current_store_id') 
            ?: ((function_exists('tenant') && tenant()) ? (
                Auth::user()?->getCurrentStore()?->id 
                ?: ($subject instanceof Invoice ? $subject->store_id : null)
                ?: ($subject instanceof CashShift ? $subject->store_id : null)
                ?: ($subject instanceof Purchase ? $subject->store_id : null)
                ?: ($subject instanceof Expense ? $subject->store_id : null)
                ?: (class_exists(Store::class) ? Store::getMainStore()?->id : null)
            ) : null);

        if ($resolvedStoreId && (!function_exists('tenant') || !tenant() || !Store::where('id', $resolvedStoreId)->exists())) {
            $resolvedStoreId = null;
        }

        return ActivityLog::create([
            'user_id'      => $resolvedUserId,
            'store_id'     => $resolvedStoreId,
            'module'       => $module,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject ? $subject->getKey() : null,
            'description'  => $description,
            'properties'   => $properties,
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::userAgent(),
        ]);
    }

    /**
     * Log Sales / Invoice operations.
     */
    public function logSales(string $action, Invoice $invoice, string $description, ?array $properties = null): ActivityLog
    {
        $defaultProps = [
            'invoice_number'  => $invoice->invoice_number,
            'customer_name'   => $invoice->customer?->name ?? 'عميل نقدي',
            'net_total'       => (string) $invoice->net_total,
            'discount_amount' => (string) $invoice->discount_amount,
            'status'          => $invoice->status,
        ];

        return $this->log(
            module: 'sales',
            action: $action,
            description: $description,
            subject: $invoice,
            properties: array_merge($defaultProps, $properties ?? []),
            storeId: $invoice->store_id
        );
    }

    /**
     * Log Shift operations.
     */
    public function logShift(string $action, CashShift $shift, string $description, ?array $properties = null): ActivityLog
    {
        $defaultProps = [
            'shift_number'         => $shift->shift_number,
            'opening_cash_balance' => (string) $shift->opening_cash_balance,
            'actual_cash_balance'  => (string) $shift->actual_cash_balance,
            'difference'           => (string) $shift->difference,
            'status'               => $shift->status,
        ];

        return $this->log(
            module: 'shifts',
            action: $action,
            description: $description,
            subject: $shift,
            properties: array_merge($defaultProps, $properties ?? []),
            storeId: $shift->store_id
        );
    }

    /**
     * Log Inventory & Item price/stock modifications.
     */
    public function logInventory(string $action, Model $subject, string $description, ?array $properties = null): ActivityLog
    {
        return $this->log(
            module: 'inventory',
            action: $action,
            description: $description,
            subject: $subject,
            properties: $properties
        );
    }

    /**
     * Log Purchase operations.
     */
    public function logPurchase(string $action, Purchase $purchase, string $description, ?array $properties = null): ActivityLog
    {
        $defaultProps = [
            'purchase_number' => $purchase->purchase_number,
            'supplier_name'   => $purchase->supplier?->name ?? '-',
            'net_total'       => (string) $purchase->net_total,
            'paid_amount'     => (string) $purchase->paid_amount,
        ];

        return $this->log(
            module: 'purchases',
            action: $action,
            description: $description,
            subject: $purchase,
            properties: array_merge($defaultProps, $properties ?? []),
            storeId: $purchase->store_id
        );
    }

    /**
     * Log Expense operations.
     */
    public function logExpense(string $action, Expense $expense, string $description, ?array $properties = null): ActivityLog
    {
        $defaultProps = [
            'expense_category' => $expense->category,
            'amount'           => (string) $expense->amount,
            'notes'            => $expense->notes,
        ];

        return $this->log(
            module: 'expenses',
            action: $action,
            description: $description,
            subject: $expense,
            properties: array_merge($defaultProps, $properties ?? []),
            storeId: $expense->store_id
        );
    }

    /**
     * Log Auth / Security actions.
     */
    public function logAuth(string $action, User $user, string $description, ?array $properties = null): ActivityLog
    {
        return $this->log(
            module: 'auth',
            action: $action,
            description: $description,
            subject: $user,
            properties: $properties,
            userId: $user->id
        );
    }

    /**
     * Log System & Contacts operations.
     */
    public function logSystem(string $action, string $description, ?Model $subject = null, ?array $properties = null): ActivityLog
    {
        return $this->log(
            module: 'system',
            action: $action,
            description: $description,
            subject: $subject,
            properties: $properties
        );
    }
}
