<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SignupBusinessType;
use App\Enums\SignupRequestStatus;
use App\Models\Concerns\UsesCentralConnection;
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
    ];

    protected function casts(): array
    {
        return [
            'business_type' => SignupBusinessType::class,
            'status' => SignupRequestStatus::class,
            'reviewed_at' => 'datetime',
            'reviewed_by' => 'integer',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SignupRequest>  $query
     * @return Builder<SignupRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SignupRequestStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === SignupRequestStatus::Pending;
    }
}
