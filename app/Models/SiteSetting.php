<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a single setting value by key, with a fallback default.
     * Cached for a day since these change rarely (admin edits only).
     *
     * Usage: SiteSetting::get('hero_title', 'Welcome to Ensalada');
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("site_setting_{$key}", now()->addDay(), function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    /**
     * Set (create or update) a setting value and clear its cache.
     *
     * Usage: SiteSetting::set('hero_title', 'Fresh Salads, Made Modern.');
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("site_setting_{$key}");
    }

    /**
     * Get all settings as a flat key => value array (useful for a single
     * "get all site content" admin/API endpoint).
     */
    public static function allAsArray(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}