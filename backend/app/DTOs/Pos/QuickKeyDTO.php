<?php

declare(strict_types=1);

namespace App\DTOs\Pos;

use App\Enums\PosQuickKeyColor;
use App\Enums\PosQuickKeyType;

/**
 * POSB-6: one validated POS quick key of a full-replace save. The FK that does not match
 * `type` is always null, whatever the client sent.
 */
final class QuickKeyDTO
{
    public function __construct(
        public readonly int $page,
        public readonly int $position,
        public readonly PosQuickKeyType $type,
        public readonly ?int $itemId,
        public readonly ?int $categoryId,
        public readonly ?string $label,
        public readonly ?PosQuickKeyColor $color,
    ) {}

    /**
     * @param  array{page: int|string, position: int|string, type: string, item_id?: int|string|null, category_id?: int|string|null, label?: string|null, color?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $type = PosQuickKeyType::from((string) $data['type']);

        $label = isset($data['label']) ? trim((string) $data['label']) : '';
        $color = isset($data['color']) && $data['color'] !== '' ? PosQuickKeyColor::from((string) $data['color']) : null;

        return new self(
            page: (int) $data['page'],
            position: (int) $data['position'],
            type: $type,
            itemId: $type === PosQuickKeyType::Item && isset($data['item_id']) ? (int) $data['item_id'] : null,
            categoryId: $type === PosQuickKeyType::Category && isset($data['category_id']) ? (int) $data['category_id'] : null,
            label: $label === '' ? null : $label,
            color: $color,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $keys  FormRequest::validated()['keys']
     * @return list<self>
     */
    public static function collection(array $keys): array
    {
        /** @var list<self> $dtos */
        $dtos = array_values(array_map(static fn (array $key): self => self::fromArray($key), $keys));

        return $dtos;
    }

    /**
     * Column => value for a `pos_quick_keys` row (store_id/timestamps added by the Action).
     *
     * @return array{page: int, position: int, type: string, item_id: int|null, category_id: int|null, label: string|null, color: string|null}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'position' => $this->position,
            'type' => $this->type->value,
            'item_id' => $this->itemId,
            'category_id' => $this->categoryId,
            'label' => $this->label,
            'color' => $this->color?->value,
        ];
    }
}
