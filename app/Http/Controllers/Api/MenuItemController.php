<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderMenuItemsRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\SyncMenuItemBranchesRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    /**
     * GET /api/menu-items?branch=beirut
     * Public: items available at the given branch, with that branch's
     * price attached, optionally filtered by category slug, tag, or
     * featured flag. A branch is required — pricing and availability
     * only make sense in the context of one.
     *
     * The available+ordered list for a branch is cached as one block,
     * then filtered in memory — this avoids needing a separate cache
     * key per filter combination (category x tag x featured), which
     * would be hard to invalidate cleanly without Redis cache tags.
     */
    public function index(Request $request)
    {
        $request->validate(['branch' => ['required', 'string']]);

        $branch = Branch::where('slug', $request->branch)->first();

        if (! $branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }

        $items = Cache::remember(
            "menu_items.public.{$branch->slug}",
            now()->addHours(6),
            function () use ($branch) {
                return MenuItem::query()
                    ->with(['category', 'tags'])
                    ->whereHas('branches', function ($q) use ($branch) {
                        $q->where('branches.id', $branch->id)
                            ->where('branch_menu_item.is_available', true);
                    })
                    ->with(['branches' => function ($q) use ($branch) {
                        $q->where('branches.id', $branch->id);
                    }])
                    ->ordered()
                    ->get()
                    ->map(function ($item) {
                        // Flatten this branch's pivot price onto the item
                        // itself so the frontend doesn't need to know
                        // about the branches relationship at all.
                        $item->price = $item->branches->first()->pivot->price;
                        unset($item->branches);

                        return $item;
                    })
                    ->toArray();
            }
        );

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
     * GET /api/menu-items/{menuItem}?branch=beirut
     * Branch is optional here; if given, the branch's price is attached.
     */
    public function show(Request $request, MenuItem $menuItem)
    {
        $menuItem->load(['category', 'tags']);

        if ($request->filled('branch')) {
            $branch = Branch::where('slug', $request->branch)->first();
            $pivot = $branch
                ? $menuItem->branches()->where('branches.id', $branch->id)->first()?->pivot
                : null;

            $menuItem->price = $pivot?->price;
            $menuItem->is_available_here = (bool) ($pivot?->is_available ?? false);
        }

        return response()->json($menuItem);
    }

    /**
     * PUT /api/admin/menu-items/{menuItem}/branches
     * Body: { "branches": [{"branch_id": 1, "price": 10, "is_available": true}, ...] }
     * Replaces this item's full set of branch assignments — a branch
     * left out simply means the item isn't offered there.
     */
    public function syncBranches(SyncMenuItemBranchesRequest $request, MenuItem $menuItem)
    {
        $sync = collect($request->validated('branches'))->mapWithKeys(fn ($row) => [
            $row['branch_id'] => [
                'price' => $row['price'],
                'is_available' => $row['is_available'] ?? true,
            ],
        ]);

        $menuItem->branches()->sync($sync);

        Branch::clearCaches();

        return response()->json($menuItem->load('branches'));
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

        Branch::clearCaches();

        return response()->json(['message' => 'Order updated']);
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
