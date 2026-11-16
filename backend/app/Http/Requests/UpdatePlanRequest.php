<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Http\Requests\Concerns\ValidatesPlanPayload;
use App\Models\Plan;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/super-admin/plans/{id}: edit every setting of a plan (rules in ValidatesPlanPayload).
 */
class UpdatePlanRequest extends FormRequest
{
    use ValidatesPlanPayload;

    private ?Plan $resolvedPlan = null;

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::PlansManage);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->planRules();
    }

    protected function storedPlan(): ?Plan
    {
        return $this->resolvedPlan ??= Plan::query()->find((int) $this->route('id'));
    }
}
