<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One picture of a memory post, stored on the "public" disk under memories/. */
class MemoryPhoto extends Model
{
    protected $fillable = ['path'];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Removes the row and its file, so deleted pictures don't stay in storage. */
    public function deleteWithFile(): void
    {
        Storage::disk('public')->delete($this->path);
        $this->delete();
    }
}
