<?php

declare(strict_types=1);

namespace App\Services\Import\Handlers;

use App\Actions\Categories\CreateCategoryAction;
use App\Models\Category;
use App\Services\Import\ImportContext;

/**
 * PRD-06: categories (unique by name, case-insensitive; an existing name is a duplicate and is skipped).
 */
final class CategoriesImportHandler extends AbstractImportHandler
{
    public function __construct(private readonly CreateCategoryAction $createCategory) {}

    public function key(): string
    {
        return 'categories';
    }

    public function columns(): array
    {
        return ['name', 'icon'];
    }

    protected function requiredColumns(): array
    {
        return ['name'];
    }

    protected function normalize(array $raw, array &$errors): array
    {
        return [
            'name' => $this->requireText($raw, 'name', 100, $errors),
            'icon' => $this->requireText($raw, 'icon', 50, $errors, false),
        ];
    }

    protected function duplicateKey(array $data): string
    {
        return mb_strtolower((string) $data['name']);
    }

    protected function existingKeys(array $keys): array
    {
        $existing = [];
        foreach (Category::query()->pluck('name') as $name) {
            $existing[mb_strtolower((string) $name)] = true;
        }

        return $existing;
    }

    public function write(array $rows, ImportContext $context): int
    {
        foreach ($rows as $row) {
            $this->createCategory->execute([
                'name' => (string) $row['name'],
                'icon' => $row['icon'],
            ]);
        }

        return count($rows);
    }
}
