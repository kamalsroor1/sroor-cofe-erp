<?php

declare(strict_types=1);

namespace App\DTOs\AccountDeletion;

final readonly class HandleAccountDeletionRequestDTO
{
    public function __construct(
        public int $requestId,
        public ?string $note,
    ) {}

    /**
     * @param  array{note?: string|null}  $data
     */
    public static function fromArray(array $data, int $requestId): self
    {
        $note = isset($data['note']) ? trim((string) $data['note']) : '';

        return new self($requestId, $note === '' ? null : $note);
    }
}
