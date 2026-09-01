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
        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.view') && !$user->can('pos.access')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        // Auto-seed missing categories or auto-link items where category_id is null
        $unlinkedCats = Item::whereNotNull('category')
            ->where('category', '!=', '')
            ->whereNull('category_id')
            ->distinct()
            ->pluck('category');

        if ($unlinkedCats->isNotEmpty()) {
            $icons = ['📱', '🎧', '🔌', '🧶', '🔋', '⌚', '🛡️', '📲', '🚗', '💾', '🔧', '🎮', '☕', '🧃', '🍰', '🥪'];
            $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#06b6d4', '#6366f1', '#14b8a6'];
            $i = Category::count();
            foreach ($unlinkedCats as $cName) {
                $cat = Category::firstOrCreate(
                    ['name' => $cName],
                    [
                        'icon'        => $icons[$i % count($icons)],
                        'color'       => $colors[$i % count($colors)],
                        'color_light' => $colors[$i % count($colors)] . '20',
                        'sort_order'  => $i + 1,
                        'is_active'   => true,
                    ]
                );
                Item::where('category', $cName)->whereNull('category_id')->update(['category_id' => $cat->id]);
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

        $totalItemsCount = Item::count();

        return response()->json([
            'success'           => true,
            'data'              => $categories,
            'total_items_count' => $totalItemsCount,
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
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $user = $request->user();
        if ($user && !$user->hasRole('admin') && !$user->can('items.delete')) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 403);
        }

        $this->deleteCategoryAction->execute($category);

        return response()->json([
            'success' => true,
            'message' => __('inventory.category_deleted_success') ?: 'تم حذف الفئة بنجاح ✓',
        ], 200);
    }
}
