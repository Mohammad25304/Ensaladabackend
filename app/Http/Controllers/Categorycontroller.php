<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private const CACHE_KEY = 'categories.public';

    /**
     * GET /api/categories
     * Public: active categories in display order, with their menu items.
     * Cached, since this changes only when an admin edits categories.
     */
    public function index(Request $request)
    {
        // Admin dashboard passes ?all=1 to see inactive categories too — skip cache for that view
        if ($request->boolean('all')) {
            return response()->json(Category::orderBy('sort_order')->get());
        }

        $categories = Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return Category::active()->get();
        });

        return response()->json($categories);
    }

    /**
     * GET /api/categories/{category}
     */
    public function show(Category $category)
    {
        return response()->json($category->load('menuItems'));
    }

    /**
     * POST /api/admin/categories
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']['en']);

        $category = Category::create($data);

        $this->clearCache();

        return response()->json($category, 201);
    }

    /**
     * PUT /api/admin/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        if (isset($data['name']['en']) && $data['name']['en'] !== ($category->name['en'] ?? null)) {
            $data['slug'] = $this->uniqueSlug($data['name']['en'], $category->id);
        }

        $category->update($data);

        $this->clearCache();

        return response()->json($category);
    }

    /**
     * DELETE /api/admin/categories/{category}
     */
    public function destroy(Category $category)
    {
        $category->delete();

        $this->clearCache();

        return response()->json(['message' => 'Category deleted']);
    }

    /**
     * POST /api/admin/categories/reorder
     * Body: { "order": [{"id": 3, "sort_order": 0}, {"id": 1, "sort_order": 1}, ...] }
     */
    public function reorder(RecordCategoriesRequest $request)
    {
        foreach ($request->validated('order') as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $this->clearCache();

        return response()->json(['message' => 'Order updated']);
    }

    /**
     * Wipe the cached public category list. Called after any admin write.
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Generate a unique slug from the category name, avoiding collisions
     * with existing rows (excluding the current row when updating).
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
