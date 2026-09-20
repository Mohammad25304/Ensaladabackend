<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Treated as a normal model (not a Pivot subclass) so Filament's
 * Repeater can manage it via a HasMany relationship — Repeaters can't
 * reliably write to a raw BelongsToMany pivot directly.
 */
class BranchMenuItem extends Model
{
    protected $table = 'branch_menu_item';

    protected $fillable = [
        'branch_id',
        'menu_item_id',
        'price',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Branch::clearCaches());
        static::deleted(fn () => Branch::clearCaches());
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
