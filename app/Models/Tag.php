<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Tags are embedded in the cached public menu lists, so renaming or
     * deleting one must clear those caches or the site shows stale tags.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Branch::clearCaches());
        static::deleted(fn () => Branch::clearCaches());
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_tag');
    }
}
