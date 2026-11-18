<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Http\Requests\Signup\StoreSignupRequestRequest;
use App\Rules\TenantDatabaseName;
use App\Support\PasswordPolicy;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * Security review (SIGN-13): the owner phone is stored in one spelling (Arabic-Indic digits,
     * separators and the +20 / 0020 prefixes folded), like the signup forms; the comparable
     * `phone_normalized` column is derived from it on save (Tenant::booted()).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => StoreSignupRequestRequest::normalizePhone($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:50|alpha_dash|unique:tenants,slug',
            'email' => 'required|email|max:255|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'plan_id' => 'required|exists:plans,id',
            'password' => ['required', 'string', PasswordPolicy::rule()],
            'custom_domain' => 'nullable|string|max:255|unique:domains,domain',
            'trial_days' => 'nullable|integer|min:0|max:90',
            // Tenant prefix, never the central DB, unique across tenants (TenantDatabaseName).
            'tenancy_db_name' => ['nullable', 'string', 'max:64', new TenantDatabaseName],
            'tenancy_db_username' => 'nullable|string|max:100',
            'tenancy_db_password' => 'nullable|string|max:255',
        ];
    }
}
