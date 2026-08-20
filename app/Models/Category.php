<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ]; 

    protected static function booted(): void
    {
        // Clear the public categories cache whenever a category changes,
        // regardless of whether the write came from the API or Filament.
        static::saved(fn () => Cache::forget('categories.public'));
        static::deleted(fn () => Cache::forget('categories.public'));
    }

    
    public function menuItems(): HasMany
    {
        return $this->hasMany(menu_item:: class);
    }
    
    public function scopeActive($query){
        return $query->where('is_active',true)->orderBy('sort_order');
    }

}
