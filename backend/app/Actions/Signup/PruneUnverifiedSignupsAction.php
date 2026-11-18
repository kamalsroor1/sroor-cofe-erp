<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\PruneSignupsResult;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * SIGN-13: the daily cleanup of central `signup_requests` (never a tenant):
 *
 *  - deletes self-signups that never verified their email, idle since before the cut-off: both
 *    created_at AND the last code sent (verification_sent_at) are older (security review: a
 *    resent code keeps the row alive; portable GREATEST() without the function). Only rows still
 *    `awaiting_verification` (they own no tenant, no subdomain hold and no verified identity);
 *  - security review: revokes the browser token of rows final for longer than
 *    signup.token_ttl_after_final_minutes (forLiveToken already refuses it; this drops the hash);
 *  - security review: scrubs final rows older than signup.scrub_after_days: every secret hash
 *    (password, token, code) and the client IP / network. The business record stays for the log.
 */
final class PruneUnverifiedSignupsAction
{
    private const CHUNK = 500;

    /** @var list<string> */
    private const FINAL = [
        SignupRequestStatus::Provisioned->value,
        SignupRequestStatus::Approved->value,
        SignupRequestStatus::Rejected->value,
        SignupRequestStatus::Failed->value,
    ];

    public function execute(CarbonInterface $olderThan): PruneSignupsResult
    {
        return new PruneSignupsResult(
            deleted: $this->deleteUnverified($olderThan),
            tokensRevoked: $this->revokeFinalTokens(),
            scrubbed: $this->scrubOldFinalRows(),
        );
    }

    private function deleteUnverified(CarbonInterface $olderThan): int
    {
        $deleted = 0;

        do {
            $ids = $this->unverifiedIdle(SignupRequest::query(), $olderThan)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $deleted += $this->unverifiedIdle(SignupRequest::query()->whereKey($ids), $olderThan)->delete();
            }
        } while (count($ids) === self::CHUNK);

        return $deleted;
    }

    /**
     * @param  Builder<SignupRequest>  $query
     * @return Builder<SignupRequest>
     */
    private function unverifiedIdle(Builder $query, CarbonInterface $olderThan): Builder
    {
        return $query
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('status', SignupRequestStatus::AwaitingVerification->value)
            ->whereNull('tenant_id')
            ->where('created_at', '<', $olderThan)
            ->where(static fn (Builder $idle) => $idle->whereNull('verification_sent_at')->orWhere('verification_sent_at', '<', $olderThan));
    }

    private function revokeFinalTokens(): int
    {
        $deadline = now()->subMinutes(max(1, (int) config('signup.token_ttl_after_final_minutes', 60)));

        return SignupRequest::query()
            ->whereIn('status', self::FINAL)
            ->whereNotNull('signup_token_hash')
            ->where('terminal_at', '<=', $deadline)
            ->toBase()
            ->update(['signup_token_hash' => null, 'updated_at' => now()]);
    }

    private function scrubOldFinalRows(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('signup.scrub_after_days', 30)));

        return SignupRequest::query()
            ->whereIn('status', self::FINAL)
            ->where('terminal_at', '<', $cutoff)
            ->where(static fn (Builder $dirty) => $dirty
                ->whereNotNull('password_hash')
                ->orWhereNotNull('signup_token_hash')
                ->orWhereNotNull('verification_code_hash')
                ->orWhereNotNull('ip')
                ->orWhereNotNull('ip_network'))
            ->toBase()
            ->update([
                'password_hash' => null,
                'signup_token_hash' => null,
                'verification_code_hash' => null,
                'ip' => null,
                'ip_network' => null,
                'updated_at' => now(),
            ]);
    }
}
