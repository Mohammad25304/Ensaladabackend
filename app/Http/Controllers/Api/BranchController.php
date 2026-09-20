<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderBranchesRequest;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    private const CACHE_KEY = 'branches.public';

    /**
     * GET /api/branches
     * Public: active branches, for the branch-picker screen.
     */
    public function index(Request $request)
    {
        // Admin dashboard passes ?all=1 to also see inactive branches
        if ($request->boolean('all')) {
            return response()->json(Branch::orderBy('sort_order')->get());
        }

        $branches = Cache::remember(
            self::CACHE_KEY,
            now()->addHours(6),
            fn () => Branch::active()->get()->toArray()
        );

        return response()->json($branches);
    }

    /**
     * GET /api/branches/{branch}
     */
    public function show(Branch $branch)
    {
        return response()->json($branch);
    }

    /**
     * POST /api/admin/branches
     */
    public function store(StoreBranchRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']['en']);
        $data['sort_order'] = (Branch::max('sort_order') ?? 0) + 1;

        $branch = Branch::create($data);

        return response()->json($branch, 201);
    }

    /**
     * PUT /api/admin/branches/{branch}
     */
    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $data = $request->validated();

        if (isset($data['name']['en']) && $data['name']['en'] !== ($branch->name['en'] ?? null)) {
            $data['slug'] = $this->uniqueSlug($data['name']['en'], $branch->id);
        }

        $branch->update($data);

        return response()->json($branch);
    }

    /**
     * DELETE /api/admin/branches/{branch}
     * The branch_menu_item pivot rows cascade-delete automatically.
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json(['message' => 'Branch deleted']);
    }

    /**
     * POST /api/admin/branches/reorder
     */
    public function reorder(ReorderBranchesRequest $request)
    {
        foreach ($request->validated('order') as $item) {
            Branch::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
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
            Branch::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
