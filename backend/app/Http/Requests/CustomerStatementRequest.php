<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Customers\CustomerStatementFilterDTO;
use App\Http\Requests\Concerns\ResolvesReportPeriod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PERF-01: GET /customers/{id}/statement — period (default: last 90 days, `period=all` for the
 * whole history) and page of the statement.
 */
final class CustomerStatementRequest extends FormRequest
{
    use ResolvesReportPeriod;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasRole('admin') || $user->can('customers.statement') || $user->can('customers.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A reversed range (start after end) is a 422.
        return $this->withEndNotBeforeStart([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'period' => ['nullable', 'string', 'in:all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.CustomerStatementFilterDTO::MAX_PER_PAGE],
        ]);
    }
}
