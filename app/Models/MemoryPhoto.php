<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One picture of a memory post, stored on the "public" disk under memories/.
 * thumb_path is its card-size copy (memories/thumbs/…jpg), or null when there is none yet.
 */
class MemoryPhoto extends Model
{
    protected $fillable = ['path', 'thumb_path'];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    /** The full-size photo: the post page and its Full size button. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** The card-size copy for cards and thumbnails; the full photo when no copy exists yet. */
    public function cardUrl(): string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : $this->url();
    }

    /** Removes the row and its files (photo and card copy), so deleted pictures don't stay in storage. */
    public function deleteWithFile(): void
    {
        Storage::disk('public')->delete(array_filter([$this->path, $this->thumb_path]));
        $this->delete();
    }
}
