<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;

class TagController extends Controller
{
    /**
     * GET /api/tags
     * Public: full tag list, e.g. to build a filter bar on the menu page.
     */
    public function index()
    {
        return response()->json(Tag::orderBy('name')->get());
    }

    /**
     * POST /api/admin/tags
     */
    public function store(StoreTagRequest $request)
    {
        $tag = Tag::create($request->validated());

        return response()->json($tag, 201);
    }

    /**
     * PUT /api/admin/tags/{tag}
     */
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return response()->json($tag);
    }

    /**
     * DELETE /api/admin/tags/{tag}
     */
    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->json(['message' => 'Tag deleted']);
    }
}