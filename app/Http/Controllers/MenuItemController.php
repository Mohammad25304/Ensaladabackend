<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderMenuItemsRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    private const CACHE_KEY = 'menu_items.public';

    /**
     * GET /api/menu-items
     * Public: available items, optionally filtered by category slug or tag.
     * ?category=signature-bowls&tag=vegan&featured=1
     *
     * The full available+ordered list is cached as one block, then filtered
     * in memory — this avoids needing a separate cache key per filter
     * combination (category x tag x featured) which would be hard to
     * invalidate cleanly without Redis cache tags.
     */
    public function index(Request $request)
    {
        $items = Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return MenuItem::query()
                ->with(['category', 'tags'])
                ->available()
                ->ordered()
                ->get()
                ->toArray();

        });

        $items = collect($items);

        if ($request->filled('category')) {
            $items = $items->filter(
                fn ($item) => ($item['category']['slug'] ?? null) === $request->category
            );
        }

        if ($request->filled('tag')) {
            $items = $items->filter(
                fn ($item) => collect($item['tags'] ?? [])
                    ->contains('name', $request->tag)
            );
        }

        if ($request->boolean('featured')) {
            $items = $items->where('is_featured', true);
        }

        return response()->json($items->values());
    }

    /**
     * GET /api/menu-items/{menuItem}
     */
    public function show(MenuItem $menuItem)
    {
        return response()->json($menuItem->load(['category', 'tags']));
    }

    /**
     * POST /api/admin/menu-items
     * Admin only. Expects multipart/form-data with an "image" file.
     */
    public function store(StoreMenuItemRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu-items', 'public');
            // $data['image'] = Storage::disk('public')->url($path);
            $data['image'] = asset('storage/'.$path);

            $data['image_public_id'] = $path;
        }

        $data['slug'] = $this->uniqueSlug($data['name']['en']);
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $menuItem = MenuItem::create($data);

        if ($tags !== null) {
            $menuItem->tags()->sync($tags);
        }

        $this->clearCache();

        return response()->json($menuItem->load(['category', 'tags']), 201);
    }

    /**
     * PUT /api/admin/menu-items/{menuItem}
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($menuItem->image_public_id) {
                Storage::disk('public')->delete($menuItem->image_public_id);
            }

            $path = $request->file('image')->store('menu-items', 'public');
            // $data['image'] = Storage::disk('public')->url($path);
            $data['image'] = asset('storage/'.$path);

            $data['image_public_id'] = $path;
        }

        if (isset($data['name']['en']) && $data['name']['en'] !== ($menuItem->name['en'] ?? null)) {
            $data['slug'] = $this->uniqueSlug($data['name']['en'], $menuItem->id);
        }

        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $menuItem->update($data);

        if ($tags !== null) {
            $menuItem->tags()->sync($tags);
        }

        $this->clearCache();

        return response()->json($menuItem->load(['category', 'tags']));
    }

    /**
     * DELETE /api/admin/menu-items/{menuItem}
     */
    public function destroy(MenuItem $menuItem)
    {
        if ($menuItem->image_public_id) {
            Storage::disk('public')->delete($menuItem->image_public_id);
        }

        $menuItem->delete();

        $this->clearCache();

        return response()->json(['message' => 'Menu item deleted']);
    }

    /**
     * POST /api/admin/menu-items/reorder
     * Body: { "order": [{"id": 5, "sort_order": 0}, ...] }
     */
    public function reorder(ReorderMenuItemsRequest $request)
    {
        foreach ($request->validated('order') as $item) {
            MenuItem::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $this->clearCache();

        return response()->json(['message' => 'Order updated']);
    }

    /**
     * Wipe the cached public menu list. Called after any admin write.
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (
            MenuItem::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
