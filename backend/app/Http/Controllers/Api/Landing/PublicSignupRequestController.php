<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landing;

use App\Actions\Signup\CreateSignupRequestAction;
use App\DTOs\Signup\CreateSignupRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signup\StoreSignupRequestRequest;
use Illuminate\Http\JsonResponse;

/**
 * PRD-05: POST /api/v1/public/signup-requests (public, throttled `signup-requests`).
 * Answers 201 with a translated thank-you message only (no ids), also for a honeypot hit.
 */
final class PublicSignupRequestController extends Controller
{
    public function __construct(
        private readonly CreateSignupRequestAction $createSignupRequest,
    ) {}

    public function store(StoreSignupRequestRequest $request): JsonResponse
    {
        /** @var array{business_name: string, contact_name: string, phone: string, email?: string|null, city?: string|null, business_type: string, plan_code?: string|null, preferred_subdomain?: string|null, notes?: string|null, website?: string|null} $data */
        $data = $request->validated();

        $this->createSignupRequest->execute(
            CreateSignupRequestDTO::fromArray($data, $request->ip(), $request->userAgent()),
        );

        return response()->json([
            'success' => true,
            'message' => __('signup.submitted'),
        ], 201);
    }
}
