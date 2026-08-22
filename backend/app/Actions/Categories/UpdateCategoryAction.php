<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;

final class UpdateCategoryAction
{
    public function execute(Category $category, array $data): Category
    {
        $category->update([
            'name'       => isset($data['name']) ? trim((string)$data['name']) : $category->name,
            'icon'       => isset($data['icon']) ? trim((string)$data['icon']) : $category->icon,
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : $category->sort_order,
            'is_active'  => isset($data['is_active']) ? (bool)$data['is_active'] : $category->is_active,
        ]);

        return $category->fresh();
    }
}