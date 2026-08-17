<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderMenuItemsRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\menu_item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
 

class MenuItemController extends Controller
{
    /**
     * GET /api/menu-items
     * Public: available items, optionally filtered by category slug or tag.
     * ?category=signature-bowls&tag=vegan&featured=1
     */
    public function index(Request $request)
    {
        $query = menu_item::query()->with(['category', 'tags'])->available()->ordered();
 
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }
 
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $request->tag));
        }
 
        if ($request->boolean('featured')) {
            $query->featured();
        }
 
        return response()->json($query->get());
    }
    /**
     * GET /api/menu-items/{menuItem}
     */
    public function show(menu_item $menuItem)
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
            $data['image'] = asset('storage/' . $path);

            $data['image_public_id'] = $path; // stored path, used to delete/replace later
        }
 
        $data['slug'] = $this->uniqueSlug($data['name']);
        $tags = $data['tags'] ?? null;
        unset($data['tags']);
 
        $menuItem = menu_item::create($data);
 
        if ($tags !== null) {
            $menuItem->tags()->sync($tags);
        }
 
        return response()->json($menuItem->load(['category', 'tags']), 201);
    }

    /**
     * PUT /api/admin/menu-items/{menuItem}
     */
   public function update(UpdateMenuItemRequest $request, menu_item $menuItem)
    {
        $data = $request->validated();
 
        if ($request->hasFile('image')) {
            // Remove the old image from disk before uploading the new one
            if ($menuItem->image_public_id) {
                Storage::disk('public')->delete($menuItem->image_public_id);
            }
 
            $path = $request->file('image')->store('menu-items', 'public');
            // $data['image'] = Storage::disk('public')->url($path);
            $data['image'] = asset('storage/' . $path);

            $data['image_public_id'] = $path;
        }
 
        if (isset($data['name']) && $data['name'] !== $menuItem->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $menuItem->id);
        }
 
        $tags = $data['tags'] ?? null;
        unset($data['tags']);
 
        $menuItem->update($data);
 
        if ($tags !== null) {
            $menuItem->tags()->sync($tags);
        }
 
        return response()->json($menuItem->load(['category', 'tags']));
    }

    /**
     * DELETE /api/admin/menu-items/{menuItem}
     */
    public function destroy(menu_item $menuItem)
    {
        if ($menuItem->image_public_id) {
            Storage::disk('public')->delete($menuItem->image_public_id);
        }
 
        $menuItem->delete();
 
        return response()->json(['message' => 'Menu item deleted']);
    }

    /**
     * POST /api/admin/menu-items/reorder
     * Body: { "order": [{"id": 5, "sort_order": 0}, ...] }
     */
   public function reorder(ReorderMenuItemsRequest $request)
    {
        foreach ($request->validated('order') as $item) {
            menu_item::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
 
        return response()->json(['message' => 'Order updated']);
    }
 
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;
 
        while (
            menu_item::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }
 
        return $slug;
    }
}