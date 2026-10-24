<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A post on the welcome page's Memories section: a title, an optional story and up to MAX_PHOTOS pictures. */
class Memory extends Model
{
    public const KIND_MEMORY = 'memory';

    public const KIND_FUNNY = 'funny';

    public const KIND_STORY = 'story';

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_MEMORY => '📸 Memory',
        self::KIND_FUNNY => '😂 Funny moment',
        self::KIND_STORY => '📝 Story',
    ];

    /**
     * Pictures one post may have. Vercel rejects request bodies over 4.5 MB, and the browser
     * shrinks each picture to about 0.5 MB, so six fit in one save.
     */
    public const MAX_PHOTOS = 6;

    protected $fillable = ['user_id', 'kind', 'title', 'story'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<MemoryPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(MemoryPhoto::class)->orderBy('id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS[self::KIND_MEMORY];
    }

    /** Newest posts first, with their pictures loaded in one query. */
    public function scopeFeed(Builder $query): Builder
    {
        return $query->with('photos')->latest()->orderByDesc('id');
    }
}
