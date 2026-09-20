<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'image',
        'image_public_id',
        'is_featured',
        'calories',
        'protein_grams',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_featured' => 'boolean',
        'calories' => 'integer',
        'protein_grams' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Branch::clearCaches());
        static::deleted(fn () => Branch::clearCaches());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'menu_item_tag');
    }

    /**
     * Branches this item is offered at, each carrying its own price and
     * availability via the pivot. An item with no rows here isn't sold
     * anywhere; one branch's pivot having is_available=false means it's
     * temporarily paused just at that branch.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_menu_item')
            ->withPivot(['price', 'is_available'])
            ->withTimestamps();
    }

    /**
     * Same underlying table as branches(), exposed as a HasMany so the
     * admin form's Repeater can manage it directly — Filament's Repeater
     * can't reliably write pivot data through a raw BelongsToMany.
     */
    public function branchMenuItems(): HasMany
    {
        return $this->hasMany(BranchMenuItem::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Always return a full, absolute URL for the image — whether it was
     * uploaded via Filament (which stores a relative path like
     * "menu-items/abc.jpg") or via the API (which already stores a full
     * URL). This keeps the frontend simple: it can always trust `image`
     * to be a directly-usable <img src> value.
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value && ! str_starts_with($value, 'http')
                ? Storage::disk('public')->url($value)
                : $value,
        );
    }
}
