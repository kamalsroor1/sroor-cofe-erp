<?php

declare(strict_types=1);

namespace App\DTOs\Imports;

use App\Enums\ImportType;

final readonly class CommitImportDTO
{
    public function __construct(
        public ImportType $type,
        public string $token,
        public bool $skipInvalid,
        public int $userId,
        public ?int $storeId,
    ) {}

    /**
     * @param  array{type: ImportType, token: string, skip_invalid?: bool|null, user_id: int, store_id: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['type'], $data['token'], (bool) ($data['skip_invalid'] ?? false), $data['user_id'], $data['store_id']);
    }
}
