<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiteSettingsRequest;
use App\Models\Branch;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SiteSettingController extends Controller
{
    /**
     * GET /api/site-settings?branch=beirut
     * Public: everything the frontend needs for hero, about, and contact
     * sections for the given branch, as a flat { key: value } object.
     */
    public function index(Request $request)
    {
        $request->validate(['branch' => ['required', 'string']]);

        $branch = Branch::where('slug', $request->branch)->first();

        if (! $branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }

        $settings = Cache::remember(
            "site_settings.public.{$branch->slug}",
            now()->addHours(6),
            fn () => SiteSetting::allAsArray($branch->id)
        );

        return response()->json($settings);
    }

    /**
     * PUT /api/admin/branches/{branch}/site-settings
     * Admin only. Body: { "hero_title": "...", "contact_phone": "...", ... }
     * Any number of keys can be updated in one request, for the given branch.
     */
    public function update(UpdateSiteSettingsRequest $request, Branch $branch)
    {
        foreach ($request->validated() as $key => $value) {
            SiteSetting::set($key, $value, $branch->id);
        }

        Cache::forget("site_settings.public.{$branch->slug}");

        return response()->json(SiteSetting::allAsArray($branch->id));
    }
}
