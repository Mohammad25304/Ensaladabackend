<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiteSettingsRequest;
use App\Models\SiteSetting;

class SiteSettingController extends Controller
{
    /**
     * GET /api/site-settings
     * Public: everything the frontend needs for hero, about, and
     * contact sections, as a flat { key: value } object.
     */
    public function index()
    {
        return response()->json(SiteSetting::allAsArray());
    }

    /**
     * PUT /api/admin/site-settings
     * Admin only. Body: { "hero_title": "...", "contact_phone": "...", ... }
     * Any number of keys can be updated in one request.
     */
    public function update(UpdateSiteSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            SiteSetting::set($key, $value);
        }

        return response()->json(SiteSetting::allAsArray());
    }
}
