<?php

declare(strict_types=1);

namespace App\DTOs\POS;

/**
 * POS-04: validated query of GET /pos/items. `perPage` is already clamped to 1..MAX_PER_PAGE.
 */
final readonly class ListPosItemsDTO
{
    public const DEFAULT_PER_PAGE = 60;

    public const MAX_PER_PAGE = 200;

    public const SORTS = ['name', 'code', 'pos_sales_count'];

    public function __construct(
        public int $storeId,
        public ?string $search,
        public ?int $categoryId,
        public bool $onlyInStock,
        public ?bool $isWeighted,
        public string $sort,
        public string $direction,
        public int $perPage,
        public int $page,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public static function fromArray(array $data, int $storeId): self
    {
        $sort = (string) ($data['sort'] ?? 'name');
        $search = isset($data['search']) ? trim((string) $data['search']) : '';

        return new self(
            storeId: $storeId,
            search: $search === '' ? null : $search,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            onlyInStock: (bool) ($data['only_in_stock'] ?? false),
            isWeighted: isset($data['is_weighted']) ? (bool) $data['is_weighted'] : null,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'name',
            direction: ($data['direction'] ?? null) === 'desc' || (! isset($data['direction']) && $sort === 'pos_sales_count') ? 'desc' : 'asc',
            perPage: max(1, min(self::MAX_PER_PAGE, (int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE))),
            page: max(1, (int) ($data['page'] ?? 1)),
        );
    }
}
