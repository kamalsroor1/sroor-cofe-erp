<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Super Admin authorization handled via middleware/roles
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:50|alpha_dash|unique:tenants,slug',
            'email' => 'required|email|max:255|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'plan_id' => 'required|exists:plans,id',
            'password' => 'required|string|min:6',
            'custom_domain' => 'nullable|string|max:255|unique:domains,domain',
            'trial_days' => 'nullable|integer|min:0|max:90',
            'tenancy_db_name' => 'nullable|string|max:100',
            'tenancy_db_username' => 'nullable|string|max:100',
            'tenancy_db_password' => 'nullable|string|max:255',
        ];
    }
}
