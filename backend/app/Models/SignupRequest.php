<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SignupBusinessType;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\Concerns\UsesCentralConnection;
use App\Support\Signup\SignupIdentity;
use App\Support\Signup\SignupNetwork;
use App\Support\Signup\SignupSecrets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Central DB: a public "request a workspace" submission (PRD-05).
 *
 * @property int $id
 * @property string $business_name
 * @property string $contact_name
 * @property string $phone
 * @property string|null $email
 * @property string|null $city
 * @property SignupBusinessType $business_type
 * @property string|null $plan_code
 * @property string|null $preferred_subdomain
 * @property string|null $notes
 * @property SignupRequestStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $tenant_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property SignupFlow $signup_flow SIGN-11
 * @property string|null $locale SIGN-11: language of the platform emails (ar / en)
 * @property string|null $password_hash SIGN-11: the owner's chosen password, hashed (never plain)
 * @property string|null $signup_token_hash SIGN-11: sha256 of the opaque browser token
 * @property string|null $verification_code_hash SIGN-11: keyed HMAC of the emailed code
 * @property Carbon|null $verification_code_expires_at
 * @property int $verification_attempts
 * @property Carbon|null $verification_sent_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $provisioning_started_at SIGN-12
 * @property Carbon|null $provisioned_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_code
 * @property Carbon|null $ready_mail_sent_at
 * @property Carbon|null $failure_mail_sent_at
 * @property string|null $canonical_email security review: SignupIdentity::canonicalEmail(email), set on save
 * @property string|null $phone_normalized security review: SignupIdentity::normalizedPhone(phone), set on save
 * @property string|null $ip_network security review: SignupNetwork::of(ip), set on save
 * @property Carbon|null $terminal_at security review: when the row reached a final state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SignupRequest extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'business_name',
        'contact_name',
        'phone',
        'email',
        'city',
        'business_type',
        'plan_code',
        'preferred_subdomain',
        'notes',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'tenant_id',
        'ip',
        'user_agent',
        'terms_accepted_at',
        'terms_version',
        'signup_flow',
        'locale',
    ];

    /** @var array<string, mixed> column defaults, so an unrefreshed model reads them too */
    protected $attributes = [
        'signup_flow' => 'assisted',
        'verification_attempts' => 0,
    ];

    /**
     * SIGN-11: secrets (hashes) never leave the model through toArray()/toJson(). They are
     * written with forceFill() by the self-signup Actions only, never mass-assigned.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'signup_token_hash',
        'verification_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => SignupBusinessType::class,
            'status' => SignupRequestStatus::class,
            'reviewed_at' => 'datetime',
            'reviewed_by' => 'integer',
            'terms_accepted_at' => 'datetime',
            'signup_flow' => SignupFlow::class,
            'verification_code_expires_at' => 'datetime',
            'verification_attempts' => 'integer',
            'verification_sent_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'provisioning_started_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'failed_at' => 'datetime',
            'ready_mail_sent_at' => 'datetime',
            'failure_mail_sent_at' => 'datetime',
            'terminal_at' => 'datetime',
        ];
    }

    /**
     * Security review: the comparable identity (one-free-shop guard) and the client network
     * (per-network cap) are derived from the raw values on every save, whatever the writer.
     */
    protected static function booted(): void
    {
        static::saving(static function (SignupRequest $signup): void {
            if ($signup->isDirty('email') || ($signup->canonical_email === null && $signup->getAttribute('email') !== null)) {
                $signup->canonical_email = SignupIdentity::canonicalEmail($signup->email);
            }
            if ($signup->isDirty('phone') || ($signup->phone_normalized === null && $signup->getAttribute('phone') !== null)) {
                $signup->phone_normalized = SignupIdentity::normalizedPhone($signup->phone);
            }
            if ($signup->isDirty('ip')) {
                $signup->ip_network = SignupNetwork::of($signup->ip);
            }
        });
    }

    /**
     * Security review: the self-signup a browser token belongs to, while that token is alive
     * (until signup.token_ttl_after_final_minutes after the row reached a final state).
     *
     * @param  Builder<SignupRequest>  $query
     * @return Builder<SignupRequest>
     */
    public function scopeForLiveToken(Builder $query, string $token): Builder
    {
        $deadline = now()->subMinutes(max(1, (int) config('signup.token_ttl_after_final_minutes', 60)));

        return $query
            ->where('signup_token_hash', SignupSecrets::hashToken($token))
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where(static fn (Builder $live) => $live->whereNull('terminal_at')->orWhere('terminal_at', '>', $deadline));
    }

    /**
     * @param  Builder<SignupRequest>  $query
     * @return Builder<SignupRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SignupRequestStatus::Pending->value);
    }

    public function isSelfSignup(): bool
    {
        return $this->signup_flow === SignupFlow::SelfSignup;
    }

    public function isPending(): bool
    {
        return $this->status === SignupRequestStatus::Pending;
    }
}
