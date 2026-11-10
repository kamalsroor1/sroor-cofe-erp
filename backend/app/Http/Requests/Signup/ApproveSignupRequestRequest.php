<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Enums\CentralPermission;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Rules\AvailableSubdomain;
use App\Support\PlatformSuperAdmin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * PRD-05: POST /api/v1/super-admin/signup-requests/{id}/approve.
 *
 * `subdomain` and `owner_email` default to what the applicant asked for / typed. A request
 * that is no longer pending is a 409 BEFORE field validation, so a second approve says
 * "already handled" instead of "subdomain taken" (the first approve took it).
 */
final class ApproveSignupRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    protected function prepareForValidation(): void
    {
        $signup = SignupRequest::query()->findOrFail((int) $this->route('id'));

        if (! $signup->isPending()) {
            throw new ConflictHttpException((string) __('signup.errors.not_pending'));
        }

        $this->merge([
            'subdomain' => $this->filled('subdomain') ? $this->input('subdomain') : $signup->preferred_subdomain,
            'plan_code' => $this->filled('plan_code') ? $this->input('plan_code') : $signup->plan_code,
            'owner_email' => $this->filled('owner_email') ? $this->input('owner_email') : $signup->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subdomain' => ['required', 'string', new AvailableSubdomain],
            'plan_code' => ['nullable', 'string', 'max:50', $this->activePlanExists()],
            'owner_email' => ['required', 'email:rfc', 'max:255', 'unique:tenants,email'],
            'owner_password' => ['required', 'string', 'max:255', Password::min(10)->letters()->numbers()],
        ];
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function activePlanExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! Plan::query()->where('slug', $value)->where('is_active', true)->exists()) {
                $fail('signup.validation.plan_unknown')->translate();
            }
        };
    }
}
