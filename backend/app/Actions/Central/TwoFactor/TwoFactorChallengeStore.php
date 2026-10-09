<?php

declare(strict_types=1);

namespace App\Actions\Central\TwoFactor;

use App\Support\TenantCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Single-use login challenges of operators with confirmed 2FA (IDEN-1.12).
 *
 * The plain challenge id (64 random chars) is only ever sent to the client; the central
 * cache stores the payload under the SHA-256 of the id (TenantCache central scope) for
 * central.two_factor_challenge_ttl_seconds (5 minutes).
 *
 * pull() removes the entry before the code is checked, so two concurrent attempts can
 * never both use one challenge. A wrong code puts it back (release()) with the attempt
 * counter raised and its ORIGINAL expiry, until central.two_factor_challenge_max_attempts.
 *
 * invalidateForUser() (password reset) burns every pending challenge of an operator at once:
 * each challenge carries the operator's challenge generation at creation time, and pull()
 * rejects a challenge whose generation is no longer the current one.
 */
final class TwoFactorChallengeStore
{
    /**
     * @return array{id: string, expires_at: Carbon}
     */
    public function create(int $userId, string $deviceName): array
    {
        $challengeId = Str::random(64);
        $expiresAt = now()->addSeconds($this->ttlSeconds());

        Cache::put($this->key($challengeId), [
            'user_id' => $userId,
            'device_name' => $deviceName,
            'expires_at' => $expiresAt->getTimestamp(),
            'attempts' => 0,
            'generation' => $this->generation($userId),
        ], $expiresAt);

        return ['id' => $challengeId, 'expires_at' => $expiresAt];
    }

    /**
     * Removes and returns a live challenge, or null (unknown, used or expired).
     *
     * @return array{user_id: int, device_name: string, expires_at: int, attempts: int, generation: int}|null
     */
    public function pull(string $challengeId): ?array
    {
        if ($challengeId === '') {
            return null;
        }

        $payload = Cache::pull($this->key($challengeId));

        if (! is_array($payload)
            || ! isset($payload['user_id'], $payload['device_name'], $payload['expires_at'], $payload['attempts'])
            || (int) $payload['expires_at'] <= now()->getTimestamp()
            || (int) ($payload['generation'] ?? 0) !== $this->generation((int) $payload['user_id'])) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'device_name' => (string) $payload['device_name'],
            'expires_at' => (int) $payload['expires_at'],
            'attempts' => (int) $payload['attempts'],
            'generation' => (int) ($payload['generation'] ?? 0),
        ];
    }

    /**
     * The operator a live challenge belongs to, WITHOUT consuming it (rate limiter key for the
     * public challenge endpoint). Null when unknown or expired.
     */
    public function peekUserId(string $challengeId): ?int
    {
        if ($challengeId === '') {
            return null;
        }

        $payload = Cache::get($this->key($challengeId));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['expires_at'])
            || (int) $payload['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        return (int) $payload['user_id'];
    }

    /**
     * Burns every pending login challenge of the operator (password reset): bumping the
     * generation makes pull() reject all challenges created before. Stored without expiry (one
     * small integer per operator who ever reset a password): an expiring counter would fall back
     * to 0 and wrongly reject challenges created after the reset.
     */
    public function invalidateForUser(int $userId): void
    {
        Cache::forever($this->generationKey($userId), $this->generation($userId) + 1);
    }

    /**
     * Puts a challenge back after a wrong code. Returns false (challenge burnt) once the
     * attempt budget is spent or it expired meanwhile.
     *
     * @param  array{user_id: int, device_name: string, expires_at: int, attempts: int, generation: int}  $payload
     */
    public function release(string $challengeId, array $payload): bool
    {
        $payload['attempts']++;

        if ($payload['attempts'] >= $this->maxAttempts() || $payload['expires_at'] <= now()->getTimestamp()) {
            return false;
        }

        Cache::put($this->key($challengeId), $payload, Carbon::createFromTimestamp($payload['expires_at']));

        return true;
    }

    private function generation(int $userId): int
    {
        return (int) Cache::get($this->generationKey($userId), 0);
    }

    private function generationKey(int $userId): string
    {
        return TenantCache::centralKey('central-2fa-challenge-generation:'.$userId);
    }

    private function key(string $challengeId): string
    {
        return TenantCache::centralKey('central-2fa-challenge:'.hash('sha256', $challengeId));
    }

    private function ttlSeconds(): int
    {
        $seconds = (int) config('central.two_factor_challenge_ttl_seconds', 300);

        return $seconds > 0 ? $seconds : 300;
    }

    private function maxAttempts(): int
    {
        $attempts = (int) config('central.two_factor_challenge_max_attempts', 5);

        return $attempts > 0 ? $attempts : 5;
    }
}
