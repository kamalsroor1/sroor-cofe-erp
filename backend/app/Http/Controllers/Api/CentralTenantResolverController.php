<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Tenants\ResolveTenantWorkspaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveTenantWorkspaceRequest;
use Illuminate\Http\JsonResponse;

final class CentralTenantResolverController extends Controller
{
    public function __construct(
        private readonly ResolveTenantWorkspaceAction $resolveAction
    ) {}

    /**
     * Resolve a tenant workspace by code/slug/domain
     */
    public function resolve(ResolveTenantWorkspaceRequest $request): JsonResponse
    {
        $code = (string) $request->validated('code');
        $result = $this->resolveAction->execute($code);

        $status = $result['status'] ?? ($result['success'] ? 200 : 400);

        return response()->json($result, $status);
    }
}
