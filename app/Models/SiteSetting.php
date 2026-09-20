<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'key',
        'value',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get a single setting value by key for a specific branch, with a
     * fallback default. Cached for a day since these change rarely.
     *
     * Usage: SiteSetting::get('hero_title', 'Welcome to Ensalada', $branchId);
     */
    public static function get(string $key, ?string $default, int $branchId): ?string
    {
        return Cache::remember("site_setting_{$branchId}_{$key}", now()->addDay(), function () use ($key, $default, $branchId) {
            return static::where('branch_id', $branchId)->where('key', $key)->value('value') ?? $default;
        });
    }

    /**
     * Set (create or update) a setting value for a branch and clear its cache.
     *
     * Usage: SiteSetting::set('hero_title', 'Fresh Salads, Made Modern.', $branchId);
     */
    public static function set(string $key, ?string $value, int $branchId): void
    {
        static::updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            ['value' => $value]
        );
        Cache::forget("site_setting_{$branchId}_{$key}");
    }

    /**
     * All settings for one branch as a flat key => value array (used for
     * the public "get this branch's site content" API endpoint and to
     * pre-fill the admin form for whichever branch is selected).
     */
    public static function allAsArray(int $branchId): array
    {
        return static::where('branch_id', $branchId)->pluck('value', 'key')->toArray();
    }
}
