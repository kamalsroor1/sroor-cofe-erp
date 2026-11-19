<?php

declare(strict_types=1);

namespace App\Http\Requests\DataExport;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PRD-17: every "Export my data" endpoint (status, request, download). A full copy of the
 * shop's data is an owner decision: the user must hold the admin role AND settings.manage.
 * No input (the download id comes from the signed route).
 */
class DataExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasRole(UserRole::ADMIN->value)
            && $user->can('settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
