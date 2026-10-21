<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    protected $fillable = ['user_id', 'title', 'banner', 'body', 'is_pinned', 'published_at'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bannerUrl(): ?string
    {
        return $this->banner ? Storage::disk('public')->url($this->banner) : null;
    }

    /** Only announcements whose publish time has arrived. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeFeed(Builder $query): Builder
    {
        return $query->published()->orderByDesc('is_pinned')->orderByDesc('published_at');
    }
}
