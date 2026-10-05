<?php

namespace App\Services\Chatbot;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Faq;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Assembles the plain-array context the ChatbotEngine works from.
 *
 * Everything here goes through the same cache entries the public API
 * endpoints use, so a chat message normally costs zero extra queries and an
 * admin edit (which clears those caches) is reflected in the bot immediately.
 */
class ChatContextBuilder
{
    public function build(?string $branchSlug): array
    {
        $branches = Cache::remember(
            'branches.public',
            now()->addHours(6),
            fn () => Branch::active()->get()->toArray()
        );

        $branch = null;
        $categories = [];
        $items = [];
        $settings = [];

        // An unknown / inactive slug (e.g. a stale value in the visitor's
        // browser) is treated as "no branch chosen" rather than an error.
        if ($branchSlug) {
            $model = Branch::where('slug', $branchSlug)->where('is_active', true)->first();

            if ($model) {
                $branch = $model->toArray();
                $categories = Category::publicForBranch($model);
                $items = MenuItem::publicForBranch($model);
                $settings = Cache::remember(
                    "site_settings.public.{$model->slug}",
                    now()->addHours(6),
                    fn () => SiteSetting::allAsArray($model->id)
                );
            }
        }

        return [
            'branch' => $branch,
            'branches' => $branches,
            'categories' => $categories,
            'items' => $items,
            'settings' => $settings,
            'faqs' => Faq::publicList(),
        ];
    }
}
