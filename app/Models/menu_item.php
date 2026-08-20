<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class menu_item extends Model
{
        use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image',
        'image_public_id',
        'is_featured',
        'is_available',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo{
        return $this->belongsTo(Category::class );
    }

    public function tags(): BelongsToMany{
        return $this->belongsToMany(Tag::class,"menu_item_tag");
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('menu_items.public'));
        static::deleted(fn () => Cache::forget('menu_items.public'));
    }

    public function scopeAvailable($query){
        return $query->where('is_available', true);
    }   

    public function scopeFeatured($query){
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query){
        return $query->orderby('sort_order');
    }

}
