<?php

namespace App\Http\Requests;

use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::check($this->user());
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:1',
            'max_stores' => 'required|integer|min:1',
            'max_items' => 'required|integer|min:1',
            'max_invoices_per_month' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
            'is_popular' => 'required|boolean',
            'features' => 'required|array',
        ];
    }
}
