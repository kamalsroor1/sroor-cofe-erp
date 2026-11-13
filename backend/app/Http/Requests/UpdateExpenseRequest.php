<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use App\Rules\DocumentDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('expenses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'cost_center' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => $this->dateIsUnchanged()
                ? ['required', 'date']
                : ['required', 'date', new DocumentDate($this->user())],
            'payment_method' => ['required', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * DATE-02: an edit that keeps the stored date (an old expense whose title or amount is fixed)
     * is allowed; moving the date goes through the same DocumentDate rule as creating one.
     * The lookup only decides which rule applies: store access is enforced by the controller.
     */
    private function dateIsUnchanged(): bool
    {
        $stored = Expense::query()->whereKey((int) $this->route('id'))->value('expense_date');
        if ($stored === null) {
            return false;
        }

        try {
            return CarbonImmutable::parse((string) $this->input('expense_date'))->toDateString()
                === CarbonImmutable::parse((string) $stored)->toDateString();
        } catch (Throwable) {
            return false;
        }
    }
}
