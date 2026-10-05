<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * An admin-managed chatbot answer. When a customer's message contains one of
 * the keywords, the chatbot replies with `answer` instead of its built-in
 * rules. A FAQ with no branch applies to every branch.
 */
class Faq extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'faqs.public';

    protected $fillable = [
        'question',
        'keywords',
        'answer',
        'branch_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',   // ["parking", "car park"]
        'answer' => 'array',     // {"en": "...", "es": "..."}
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Active FAQs as plain arrays for the chatbot engine, cached since they
     * only change when an admin edits them.
     */
    public static function publicList(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            return static::active()
                ->get(['id', 'keywords', 'answer', 'branch_id', 'sort_order'])
                ->toArray();
        });
    }
}
