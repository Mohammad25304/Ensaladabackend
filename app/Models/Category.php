<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',        // {"en": "...", "es": "..."}
        'description' => 'array', // {"en": "...", "es": "..."}
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('categories.public');
            Branch::clearCaches();
        });
        static::deleted(function () {
            Cache::forget('categories.public');
            Branch::clearCaches();
        });
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Active categories that have at least one available item at the branch.
     * Shared by the public categories endpoint and the chatbot.
     */
    public static function publicForBranch(Branch $branch): array
    {
        return Cache::remember(
            "categories.public.{$branch->slug}",
            now()->addHours(6),
            function () use ($branch) {
                return static::active()
                    ->whereHas('menuItems.branches', function ($q) use ($branch) {
                        $q->where('branches.id', $branch->id)
                            ->where('branch_menu_item.is_available', true);
                    })
                    ->get()
                    ->toArray();
            }
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
