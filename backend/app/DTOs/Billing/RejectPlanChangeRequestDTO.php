<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

/**
 * Input of RejectPlanChangeRequestAction: the reviewer's reason (3-500 characters, shown to the shop).
 */
final readonly class RejectPlanChangeRequestDTO
{
    public function __construct(public int $requestId, public string $reason) {}

    /**
     * @param  array<string, mixed>  $data  validated RejectPlanChangeRequestRequest data
     */
    public static function fromArray(array $data, int $requestId): self
    {
        return new self($requestId, trim((string) $data['reason']));
    }
}
