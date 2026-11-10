<?php

declare(strict_types=1);

namespace App\DTOs\Imports;

use App\Enums\ImportType;
use Illuminate\Http\UploadedFile;

final readonly class PreviewImportDTO
{
    public function __construct(
        public ImportType $type,
        public UploadedFile $file,
        public int $userId,
    ) {}

    /**
     * @param  array{type: ImportType, file: UploadedFile, user_id: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['type'], $data['file'], $data['user_id']);
    }
}
