<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStoreUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('stores.manage') ?? false;
    }

    /**
     * SEC-21 (5): the list replaces the branch's users (sync), so it must be sent explicitly.
     * A missing or null `user_ids` (an empty body) is a 422 instead of silently unassigning
     * everybody; clearing the branch on purpose sends `user_ids: []` (the Stores screen does).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
