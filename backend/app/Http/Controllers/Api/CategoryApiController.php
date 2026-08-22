<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CategoryApiController extends Controller
{
    public function __construct(
        private readonly CreateCategoryAction $createCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction
    ) {}

    /**
     * List all categories with items count
     */
    public function index(Request $request): JsonResponse
    {
        if (Category::count() === 0) {
            $distinctCats = Item::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category');
            $icons = ['☕', '🧃', '🍰', '🥪', '🍪', '🫘', '🥤', '🧊', '🎁', '📦'];
            $i = 0;
            foreach ($distinctCats as $cName) {
                $icon = $icons[$i % count($icons)];
                $cat = Category::create([
                    'name'       => $cName,
                    'icon'       => $icon,
                    'sort_order' => $i,
                    'is_active'  => true,
                ]);
                Item::where('category', $cName)->update(['category_id' => $cat->id]);
                $i++;
            }
        }

        $query = Category::withCount('items');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ], 200);
    }

    /**
     * Store new category
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->createCategoryAction->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('inventory.category_created_success') ?: 'تم إنشاء الفئة بنجاح ✓',
            'data'    => $category->loadCount('items'),
        ], 201);
    }

    /**
     * Update category
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $updated = $this->updateCategoryAction->execute($category, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('inventory.category_updated_success') ?: 'تم تعديل الفئة بنجاح ✓',
            'data'    => $updated->loadCount('items'),
        ], 200);
    }

    /**
     * Delete category
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $this->deleteCategoryAction->execute($category);

        return response()->json([
            'success' => true,
            'message' => __('inventory.category_deleted_success') ?: 'تم حذف الفئة بنجاح ✓',
        ], 200);
    }
}