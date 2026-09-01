<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;

final class CreateCategoryAction
{
    public function execute(array $data): Category
    {
        return Category::create([
            'name'       => trim((string)$data['name']),
            'icon'       => !empty($data['icon']) ? trim((string)$data['icon']) : '☕',
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
            'is_active'  => isset($data['is_active']) ? (bool)$data['is_active'] : true,
        ]);
    }
}