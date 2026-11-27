<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\MonitoringTool;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/super-admin/telescope-link — which monitoring dashboard the single-use
 * signed link opens. `tool` is optional: an old console build that sends nothing keeps
 * opening Telescope.
 */
final class IssueMonitoringLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::MonitoringView);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tool' => ['sometimes', 'string', Rule::enum(MonitoringTool::class)],
        ];
    }

    public function tool(): MonitoringTool
    {
        return MonitoringTool::from((string) $this->validated('tool', MonitoringTool::Telescope->value));
    }
}
