<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\Item;

final class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        Item::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();
    }
}