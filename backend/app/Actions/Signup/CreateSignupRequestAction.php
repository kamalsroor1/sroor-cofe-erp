<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\CreateSignupRequestDTO;
use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Models\SignupRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * PRD-05: store a public signup request (central DB) and notify the platform owner.
 *
 * A filled honeypot stores nothing and returns null: the controller still answers 201, so
 * a bot cannot tell it was detected. The notification is queued after the insert and any
 * dispatch failure is swallowed (logged by class only).
 */
final class CreateSignupRequestAction
{
    public function execute(CreateSignupRequestDTO $dto): ?SignupRequest
    {
        if ($dto->honeypotFilled) {
            return null;
        }

        $pending = SignupRequest::query()->where('status', SignupRequestStatus::Pending->value)->count();
        if ($pending >= max(1, (int) config('signup.max_pending', 500))) {
            throw new HttpException(503, (string) __('signup.errors.queue_full'), null, ['Retry-After' => '3600']);
        }

        $request = SignupRequest::query()->create([
            'business_name' => $dto->businessName,
            'contact_name' => $dto->contactName,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'city' => $dto->city,
            'business_type' => $dto->businessType->value,
            'plan_code' => $dto->planCode,
            'preferred_subdomain' => $dto->preferredSubdomain,
            'notes' => $dto->notes,
            'status' => SignupRequestStatus::Pending->value,
            'ip' => $dto->ip,
            'user_agent' => $dto->userAgent,
        ]);

        // Backlog: one summary alert per hour instead of a Telegram message per request.
        $summary = ($pending + 1) > (int) config('signup.quiet_above_pending', 50);
        if ($summary && ! Cache::add('signup:backlog-alert', true, now()->addHour())) {
            return $request;
        }

        try {
            NotifyPlatformOfSignupRequestJob::dispatch($request->id, $summary)->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Signup request notification could not be queued', ['exception' => $e::class]);
        }

        return $request;
    }
}
