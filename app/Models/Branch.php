<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array', // {"en": "Beirut", "es": "Beirut"}
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCaches());
        static::deleted(fn () => self::clearCaches());
    }

    /**
     * Menu items offered at this branch, with this branch's price and
     * availability attached via the pivot.
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'branch_menu_item')
            ->withPivot(['price', 'is_available'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * The public menu-items and categories endpoints are cached per
     * branch. Without cache tags (plain file/database cache driver),
     * the simplest reliable way to invalidate all of them is to loop
     * over every branch and forget its key whenever branches or their
     * menu assignments change.
     */
    public static function clearCaches(): void
    {
        Cache::forget('branches.public');

        static::pluck('slug')->each(function (string $slug) {
            Cache::forget("menu_items.public.{$slug}");
            Cache::forget("categories.public.{$slug}");
        });
    }
}
